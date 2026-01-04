// theme.js - Shared theme functionality across all pages

class ThemeManager {
    constructor() {
        this.init();
    }

    init() {
        this.applySavedTheme();
        this.setupThemeToggle();
    }

    getCurrentTheme() {
        // Check localStorage first, then cookie, then system preference
        try {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) return savedTheme;
        } catch (e) {}

        // Check cookie as fallback
        const cookieTheme = this.getCookie('theme');
        if (cookieTheme) return cookieTheme;

        // Default to system preference
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    }

    applySavedTheme() {
        const theme = this.getCurrentTheme();
        this.setTheme(theme);
    }

    setTheme(theme) {
        const body = document.body;
        const isDark = theme === 'dark';
        
        // Update body class
        if (isDark) {
            body.classList.add('dark');
        } else {
            body.classList.remove('dark');
        }

        // Update theme attribute for consistency
        document.documentElement.setAttribute('data-theme', theme);

        // Update toggle button icon
        this.updateToggleIcon(isDark);

        // Save preference
        this.saveThemePreference(theme);
    }

    updateToggleIcon(isDark) {
        const toggleBtn = document.getElementById('modeToggle');
        if (toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                if (isDark) {
                    icon.classList.replace('fa-moon', 'fa-sun');
                } else {
                    icon.classList.replace('fa-sun', 'fa-moon');
                }
            }
        }
    }

    setupThemeToggle() {
        const toggleBtn = document.getElementById('modeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.toggleTheme();
            });
        }
    }

    toggleTheme() {
        const currentTheme = this.getCurrentTheme();
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
    }

    saveThemePreference(theme) {
        // Save to localStorage
        try {
            localStorage.setItem('theme', theme);
        } catch (e) {}

        // Save to cookie (expires in 1 year)
        this.setCookie('theme', theme, 365);
    }

    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    setCookie(name, value, days) {
        const date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = `${name}=${value};expires=${date.toUTCString()};path=/`;
    }
}

// Initialize theme manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.themeManager = new ThemeManager();
});