import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import i18n, { changerLangue } from '@/i18n';
import MonthsTable from './MonthsTable.vue';

const n = (s) => s.replace(/[  ]/g, ' ');

const mensuelles = [
    { annee: 2026, mois: 4, minutes_total: 7680, minutes_conge: 960, minutes_maladie: 360, minutes_dispo_epi: 6360, minutes_formation: 240, minutes_supervision: 120, minutes_activite_interne: '120.00', minutes_dispo_client: '5880.00', minutes_prestations_facturables: '4920.00', nb_prestations: '84.00', ratio: '0.8367', objectif_atteint: true },
    { annee: 2026, mois: 5, minutes_total: 0, minutes_conge: 0, minutes_maladie: 0, minutes_dispo_epi: 0, minutes_formation: 0, minutes_supervision: 0, minutes_activite_interne: '0.00', minutes_dispo_client: '0.00', minutes_prestations_facturables: '0.00', nb_prestations: '0.00', ratio: null, objectif_atteint: null },
];

describe('MonthsTable', () => {
    it('affiche les mois en heures avec le statut de l\'objectif en texte, jamais en couleur seule', () => {
        changerLangue('fr');
        const wrapper = mount(MonthsTable, { props: { mensuelles }, global: { plugins: [i18n] } });

        const lignes = wrapper.findAll('tbody tr');
        expect(lignes).toHaveLength(2);
        expect(lignes[0].text()).toContain('Avril');
        expect(lignes[0].text()).toContain('128h00');
        expect(lignes[0].text()).toContain('98h00');
        expect(n(lignes[0].text())).toContain('83,7 %');
        expect(lignes[0].text()).toContain('atteint');
        expect(lignes[1].text()).toContain('non calculable');
        expect(lignes[1].text()).toContain('—');
    });

    it('se traduit en allemand et en anglais', () => {
        changerLangue('de');
        const de = mount(MonthsTable, { props: { mensuelles }, global: { plugins: [i18n] } });
        expect(de.text()).toContain('April');
        expect(de.text()).toContain('erreicht');
        expect(de.text()).toContain('nicht berechenbar');

        changerLangue('en');
        const en = mount(MonthsTable, { props: { mensuelles }, global: { plugins: [i18n] } });
        expect(en.text()).toContain('Month');
        expect(en.text()).toContain('reached');
        expect(n(en.text())).toContain('83.7%');
    });
});
