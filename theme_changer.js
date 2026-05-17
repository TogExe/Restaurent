// theme_changer.js

document.addEventListener("DOMContentLoaded", () => {
    initThemeSwitcher();
    updateCartCounter(); // Met à jour la bulle navbar depuis le localStorage
});

/* =========================================
   1. GESTION DU THÈME (Clair / Sombre)
========================================= */
function initThemeSwitcher() {
    const themeToggleBtn = document.getElementById("theme-toggle");

    const currentTheme = localStorage.getItem("theme") || "dark";
    document.documentElement.setAttribute("alt-theme", currentTheme);

    if (themeToggleBtn) {
        themeToggleBtn.addEventListener("click", () => {
            const current = document.documentElement.getAttribute("alt-theme");
            const newTheme = current === "dark" ? "light" : "dark";
            document.documentElement.setAttribute("alt-theme", newTheme);
            localStorage.setItem("theme", newTheme);
        });
    }
}

/* =========================================
   2. BULLE PANIER (navbar uniquement)
   Le panier complet est géré dans menu.php.
   Ici on lit juste le localStorage pour
   afficher le bon chiffre sur toutes les pages.
========================================= */
function updateCartCounter() {
    const countElement = document.getElementById("cart-count");
    if (!countElement) return;

    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    countElement.textContent = totalItems;
    countElement.style.display = totalItems > 0 ? "inline-block" : "none";
}

