import type { InjectionKey } from 'vue';

/** O `GroupLayout` conta pra tela se ela chegou deslizando de outra aba. */
export const groupNavigationKey: InjectionKey<{
    isSlideNavigation: () => boolean;
}> = Symbol('groupNavigation');
