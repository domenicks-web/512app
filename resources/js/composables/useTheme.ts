import { onMounted, ref } from 'vue';

const isDark = ref(false);

export function useTheme() {
    onMounted(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });

    function toggle() {
        const next = !isDark.value;

        isDark.value = next;
        document.documentElement.classList.toggle('dark', next);
        localStorage.theme = next ? 'dark' : 'light';
    }

    return { isDark, toggle };
}
