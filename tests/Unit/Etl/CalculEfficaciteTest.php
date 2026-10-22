<?php

namespace Tests\Unit\Etl;

use App\Etl\CalculEfficacite;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * JEU D'ESSAI DE RÉFÉRENCE — fonctionnalité la plus représentative du projet.
 *
 * Données d'entrée : exemple 1 du fichier « Données app EPI.xlsx » de la Fondation
 * (avril 2026, un salarié AISP — nom retiré). Résultat attendu : calculé à la main
 * dans le fichier de la Fondation. Résultat obtenu : celui du moteur.
 */
class CalculEfficaciteTest extends TestCase
{
    private function exempleFondation(): CalculEfficacite
    {
        return new CalculEfficacite(
            minutesNormales: 100 * 60,      // Heures normales            100 h
            minutesConge: (12 + 4) * 60,    // Congés annuels 12 h + récupération de solde 4 h
            minutesMaladie: 6 * 60,         // Maladie                      6 h
            minutesFormation: 4 * 60,       // Congé formation ministère    4 h
            minutesSupervision: 2 * 60,     // Supervision individuelle     2 h
            nbPrestations: '84.00',
            minutesFacturables: '4920.00',  // Prestations facturables     82 h
            minutesActiviteInterne: '120.00', // Activité interne           2 h
        );
    }

    #[Test]
    public function jeu_d_essai_de_la_fondation_cascade_complete(): void
    {
        $c = $this->exempleFondation();

        $this->assertSame(128 * 60, $c->minutesTotal(), 'Total hrs dispo = 128 h');
        $this->assertSame(106 * 60, $c->minutesDispoEpi(), 'Hrs dispo EPI = 128 − 16 − 6 = 106 h');
        $this->assertSame('5880.00', $c->minutesDispoClient(), 'Hrs dispo client = 106 − 4 − 2 − 2 = 98 h');
        $this->assertSame('0.8367', $c->ratio(), 'Efficacité = 82 / 98 = 0,8367 (valeur du fichier : 0,8367346…)');
        $this->assertSame('0.6562', $c->ratioBrut(), 'Efficacité brute = (82 + 2) / 128 = 0,65625');
    }

    #[Test]
    public function jeu_d_essai_total_epi_premier_trimestre_2026(): void
    {
        // Exemple 2 du fichier : vue « Total EPI », janvier-mars 2026 (en heures dans le fichier).
        $c = new CalculEfficacite(
            minutesNormales: 9000 * 60,
            minutesConge: (90 + 40 + 4) * 60,     // congés annuels + récupération + exceptionnels = 134 h
            minutesMaladie: 20 * 60,
            minutesFormation: (12 + 12 + 30) * 60, // formation ministère + EPI + standard = 54 h
            minutesSupervision: (12 + 12) * 60,    // individuelle + groupe = 24 h
            minutesFacturables: (string) (7220 * 60).'.00',
            minutesActiviteInterne: (string) (226 * 60).'.00',
        );

        $this->assertSame(9232 * 60, $c->minutesTotal());
        $this->assertSame(9078 * 60, $c->minutesDispoEpi());
        $this->assertSame((8774 * 60).'.00', $c->minutesDispoClient());
        $this->assertSame('0.8228', $c->ratio(), 'Efficacité réelle du fichier : 0,8228857…');
        $this->assertSame('0.8065', $c->ratioBrut(), 'Efficacité brute du fichier : 0,8065424…');
    }

    #[Test]
    public function le_ratio_est_null_quand_le_denominateur_est_nul_ou_negatif(): void
    {
        $this->assertNull((new CalculEfficacite)->ratio());
        $this->assertNull((new CalculEfficacite)->ratioBrut());

        // Plus d'activité interne que d'heures pointées : dispo client négative → non calculable,
        // jamais un ratio négatif (la formation, elle, fait partie du total et ne peut pas le rendre négatif).
        $negatif = new CalculEfficacite(minutesNormales: 60, minutesActiviteInterne: '120.00', minutesFacturables: '30.00');
        $this->assertSame('-60.00', $negatif->minutesDispoClient());
        $this->assertNull($negatif->ratio());
        $this->assertSame('2.5000', $negatif->ratioBrut(), 'la brute reste calculable sur le total : (30 + 120) / 60');
    }

    #[Test]
    public function les_minutes_de_prestations_sont_des_decimaux_exacts(): void
    {
        // 0,25 forfait × 30 min = 7,50 min : aucun arrondi flottant.
        $c = new CalculEfficacite(minutesNormales: 60, minutesFacturables: '7.50');

        $this->assertSame('0.1250', $c->ratio());
        $this->assertSame('60.00', $c->minutesDispoClient());
    }

    #[Test]
    public function les_attributs_couvrent_toutes_les_colonnes_du_cache(): void
    {
        $attributs = $this->exempleFondation()->attributs();

        foreach ([
            'minutes_total', 'minutes_normales', 'minutes_conge', 'minutes_maladie', 'minutes_formation',
            'minutes_supervision', 'minutes_deplacement', 'minutes_autres', 'nb_prestations',
            'minutes_prestations_facturables', 'minutes_activite_interne', 'minutes_dispo_epi',
            'minutes_dispo_client', 'ratio', 'ratio_brut',
        ] as $colonne) {
            $this->assertArrayHasKey($colonne, $attributs);
        }
    }
}
