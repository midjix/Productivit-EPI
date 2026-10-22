<?php

namespace App\Etl\Connecteurs;

use App\Enums\SourceImport;
use App\Etl\Contracts\ConnecteurDsk;
use App\Etl\Dto\CompteDskDto;
use App\Etl\Dto\DepartementDsk;
use App\Etl\Dto\MinutesCompteDsk;
use App\Etl\Dto\SalarieDsk;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * Adaptateur HTTP pour l'API REST DSK (spécification OpenAPI « DSK API v2 »).
 *
 *  - Authentification : POST /login {login, password} → token Bearer avec `validUntil`
 *    (mis en cache jusqu'à expiration ; un token statique DSK_API_TOKEN est accepté).
 *  - Toutes les ressources renvoient {meta: {page?}, data: [...]} ; la pagination
 *    (`page`, `count`) est parcourue jusqu'à `lastIdx`.
 *  - Un 401 en cours d'import provoque UNE nouvelle connexion puis une nouvelle tentative.
 *  - Lecture seule par construction : seul POST /login est émis en écriture.
 */
class DskHttp implements ConnecteurDsk
{
    private const CLE_CACHE_TOKEN = 'dsk.api.token';

    private const FORMAT_DATE = 'Y-m-d';

    private const TAILLE_PAGE = 500;

    /** @param  array{url: string, prefix: string, login: ?string, password: ?string, token: ?string, timeout: int}  $config */
    public function __construct(
        private readonly Http $http,
        private readonly Cache $cache,
        private readonly array $config,
    ) {}

    public function source(): SourceImport
    {
        return SourceImport::Dsk;
    }

    public function verifierConnexion(): void
    {
        $reponse = $this->client()->get('/test');

        if (! $reponse->successful()) {
            throw new RuntimeException('DSK injoignable ou authentification refusée (HTTP '.$reponse->status().').');
        }
    }

    public function departements(): iterable
    {
        foreach ($this->lire('/departments') as $d) {
            yield new DepartementDsk(
                codeDept: (string) $d['codeDept'],
                libelle: $this->libelle($d['caption'] ?? [], (string) $d['codeDept']),
                actif: (bool) ($d['activ'] ?? true),
            );
        }
    }

    public function comptes(): iterable
    {
        foreach ($this->lire('/accounts') as $c) {
            yield new CompteDskDto(
                noAccount: (int) $c['noAccount'],
                libelle: $this->libelle($c['caption'] ?? [], 'Compte '.$c['noAccount']),
                type: isset($c['type']) ? (int) $c['type'] : null,
            );
        }
    }

    public function salaries(CarbonInterface $du, CarbonInterface $au): iterable
    {
        $query = ['dateFrom' => $du->format(self::FORMAT_DATE), 'dateTo' => $au->format(self::FORMAT_DATE)];

        foreach ($this->lire('/listAll/details', $query) as $ligne) {
            // La ressource « details » expose bien plus que nécessaire (adresse, IBAN, naissance…) :
            // on ne lit QUE les champs utiles, le reste n'est jamais conservé.
            $p = $ligne['pers'] ?? $ligne;
            $nom = trim(($p['firstName'] ?? '').' '.mb_strtoupper((string) ($p['lastName'] ?? '')));

            yield new SalarieDsk(
                noPers: (int) $p['noPers'],
                nomComplet: $nom !== '' ? $nom : (string) ($p['fullName'] ?? 'Salarié '.$p['noPers']),
                codePers: $this->chaine($p['codePers'] ?? null),
                codeDept: $this->chaine($p['codeDept'] ?? null),
                wageNum: $this->chaine($p['wageNum'] ?? null),
                email: $this->chaine($p['email'] ?? null),
                dateEntree: $this->date($p['startDateRef'] ?? null),
                dateSortie: $this->date($p['endDateRef'] ?? null),
            );
        }
    }

    public function minutesParJour(CarbonInterface $du, CarbonInterface $au): iterable
    {
        $query = ['dateFrom' => $du->format(self::FORMAT_DATE), 'dateTo' => $au->format(self::FORMAT_DATE)];

        foreach ($this->lire('/accounts/calc/all', $query) as $jour) {
            $date = $this->date($jour['theDate'] ?? null);

            if ($date === null) {
                continue;
            }

            foreach ($jour['accounts'] ?? [] as $compte) {
                yield new MinutesCompteDsk(
                    noPers: (int) $jour['noPers'],
                    date: $date,
                    noAccount: (int) $compte['noAccount'],
                    minutes: (int) round((float) ($compte['value'] ?? 0)),
                );
            }
        }
    }

    // ---- Transport ---------------------------------------------------------

    /**
     * Lit une ressource paginée et renvoie les éléments de `data`, page après page.
     *
     * @param  array<string, string|int>  $query
     * @return iterable<array<string, mixed>>
     */
    private function lire(string $chemin, array $query = []): iterable
    {
        $page = 1;

        do {
            $json = $this->getJson($chemin, $query + ['page' => $page, 'count' => self::TAILLE_PAGE]);

            foreach ($json['data'] ?? [] as $element) {
                yield $element;
            }

            $pagination = $json['meta']['page'] ?? null;
            $derniere = (int) ($pagination['lastIdx'] ?? $page);
            $page++;
        } while ($pagination !== null && $page <= $derniere);
    }

    /**
     * @param  array<string, string|int>  $query
     * @return array<string, mixed>
     */
    private function getJson(string $chemin, array $query): array
    {
        $reponse = $this->client()->get($chemin, $query);

        if ($reponse->status() === 401 && empty($this->config['token'])) {
            $this->cache->forget(self::CLE_CACHE_TOKEN);
            $reponse = $this->client()->get($chemin, $query);
        }

        try {
            return $reponse->throw()->json() ?? [];
        } catch (RequestException $e) {
            throw new RuntimeException("DSK GET {$chemin} : HTTP {$e->response->status()}", previous: $e);
        }
    }

    private function client(): PendingRequest
    {
        return $this->clientAnonyme()->withToken($this->token());
    }

    private function clientAnonyme(): PendingRequest
    {
        $base = rtrim($this->config['url'], '/').'/'.trim($this->config['prefix'], '/');

        return $this->http->baseUrl($base)->acceptJson()->timeout($this->config['timeout']);
    }

    private function token(): string
    {
        if (! empty($this->config['token'])) {
            return $this->config['token'];
        }

        $enCache = $this->cache->get(self::CLE_CACHE_TOKEN);

        return is_string($enCache) && $enCache !== '' ? $enCache : $this->seConnecter();
    }

    private function seConnecter(): string
    {
        if (empty($this->config['login']) || empty($this->config['password'])) {
            throw new RuntimeException('Identifiants DSK absents (DSK_API_LOGIN / DSK_API_PASSWORD).');
        }

        $json = $this->clientAnonyme()
            ->post('/login', ['login' => $this->config['login'], 'password' => $this->config['password']])
            ->throw()
            ->json() ?? [];

        if (empty($json['token'])) {
            throw new RuntimeException('Connexion DSK refusée : '.($json['failCode'] ?? '?').' '.($json['failMsg'] ?? ''));
        }

        $expire = isset($json['validUntil']) ? CarbonImmutable::parse($json['validUntil'])->subMinute() : now()->addMinutes(30);
        $this->cache->put(self::CLE_CACHE_TOKEN, (string) $json['token'], $expire);

        return (string) $json['token'];
    }

    // ---- Helpers de lecture ---------------------------------------------------

    /** @param  array<int, array{langID?: int, caption?: string}>  $captions */
    private function libelle(array $captions, string $defaut): string
    {
        foreach ($captions as $c) {
            if (! empty($c['caption'])) {
                return trim((string) $c['caption']);
            }
        }

        return $defaut;
    }

    private function chaine(mixed $v): ?string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return $s === '' ? null : $s;
    }

    private function date(mixed $v): ?CarbonImmutable
    {
        $s = $this->chaine($v);

        return $s === null ? null : CarbonImmutable::parse($s)->startOfDay();
    }
}
