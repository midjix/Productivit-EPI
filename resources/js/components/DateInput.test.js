import { afterEach, describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import i18n, { changerLangue } from '@/i18n';
import DateInput from './DateInput.vue';

function monter(props = {}) {
    return mount(DateInput, { props: { id: 'd', modelValue: '2026-01-05', ...props }, global: { plugins: [i18n] } });
}

describe('DateInput', () => {
    afterEach(() => changerLangue('fr'));

    it('écrit la date dans la langue de l\'application, pas celle du navigateur', async () => {
        changerLangue('fr');
        const wrapper = monter();
        const champ = wrapper.get('input[type="text"]');
        expect(champ.element.value).toBe('05/01/2026');
        expect(champ.attributes('placeholder')).toBe('JJ/MM/AAAA');

        changerLangue('de');
        await wrapper.vm.$nextTick();
        expect(champ.element.value).toBe('05.01.2026');
        expect(champ.attributes('placeholder')).toBe('TT.MM.JJJJ');

        changerLangue('en');
        await wrapper.vm.$nextTick();
        expect(champ.element.value).toBe('05/01/2026');
        expect(champ.attributes('placeholder')).toBe('DD/MM/YYYY');
        expect(wrapper.get('button').attributes('aria-label')).toBe('Open the calendar');
    });

    it('émet la valeur en ISO dès qu\'une date complète est tapée, et normalise en quittant le champ', async () => {
        const wrapper = monter();
        const champ = wrapper.get('input[type="text"]');

        await champ.setValue('31/12/2026');
        expect(wrapper.emitted('update:modelValue')).toEqual([['2026-12-31']]);

        await wrapper.setProps({ modelValue: '2026-12-31' });
        await champ.setValue('5.1.2026'); // séparateur d'une autre langue, sans zéros : accepté
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2026-01-05']);
        await wrapper.setProps({ modelValue: '2026-01-05' });
        await champ.trigger('blur');
        expect(champ.element.value).toBe('05/01/2026');

        await champ.setValue('');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['']);
    });

    it('signale une date impossible sans l\'émettre et revient à la dernière date valide', async () => {
        const wrapper = monter();
        const champ = wrapper.get('input[type="text"]');

        await champ.setValue('31/02/2026');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
        expect(champ.attributes('aria-invalid')).toBe('true');

        await champ.trigger('blur');
        expect(champ.element.value).toBe('05/01/2026');
        expect(champ.attributes('aria-invalid')).toBe('false');
    });

    it('suit le calendrier natif et la valeur venue de la page', async () => {
        const wrapper = monter();
        await wrapper.get('input[type="date"]').setValue('2026-03-15');
        expect(wrapper.emitted('update:modelValue').at(-1)).toEqual(['2026-03-15']);

        await wrapper.setProps({ modelValue: '2026-03-15' });
        expect(wrapper.get('input[type="text"]').element.value).toBe('15/03/2026');

        await wrapper.setProps({ invalide: true });
        expect(wrapper.get('input[type="text"]').attributes('aria-invalid')).toBe('true');
    });
});
