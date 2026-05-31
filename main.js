document.addEventListener("DOMContentLoaded", () => {
    initThemeSwitcher();
    initCartSystem();
});

/* =========================================
   1. THEME SWITCHER: preserve the user's theme preference in localStorage
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
   2. CART SYSTEM: sidebar display and localStorage shopping cart persistence
========================================= */
function initCartSystem() {
    updateCartCounter();

    // Cache cart sidebar and interaction DOM elements
    const sidebar = document.getElementById("cart-sidebar");
    const overlay = document.getElementById("cart-overlay");
    const closeBtn = document.getElementById("cart-close-btn");
    const fabBtn = document.getElementById("cart-fab");
    const addToCartBtns = document.querySelectorAll(".add-to-cart-btn");

    // Attach open/close toggle handlers for the cart panel
    if (fabBtn) fabBtn.addEventListener("click", toggleCart);
    if (closeBtn) closeBtn.addEventListener("click", toggleCart);
    if (overlay) overlay.addEventListener("click", toggleCart);

    function toggleCart() {
        if(sidebar && overlay) {
            sidebar.classList.toggle("open");
            overlay.classList.toggle("open");
            if(sidebar.classList.contains("open")) {
                renderCartSidebar();
            }
        }
    }

    // Add item to cart and persist the updated localStorage state
    addToCartBtns.forEach(btn => {
        btn.addEventListener("click", (e) => {
            const id = btn.getAttribute("data-id");
            const name = btn.getAttribute("data-name");
            const price = parseFloat(btn.getAttribute("data-price"));

            let cart = JSON.parse(localStorage.getItem("cart")) || [];
            let existingItem = cart.find(item => item.id === id);

            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({ id, name, price, quantity: 1 });
            }

            localStorage.setItem("cart", JSON.stringify(cart));
            updateCartCounter();
            
            // Visual feedback
            btn.classList.add("added");
            btn.textContent = "✔ Ajouté";
            setTimeout(() => {
                btn.classList.remove("added");
                btn.textContent = "+ Ajouter";
            }, 1200);
        });
    });
}

function updateCartCounter() {
    const countElementNav = document.getElementById("cart-count");
    const countElementFab = document.getElementById("cart-fab-count");
    
    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);

    if (countElementNav) {
        countElementNav.textContent = totalItems;
        countElementNav.style.display = totalItems > 0 ? "inline-block" : "none";
    }
    
    if (countElementFab) {
        countElementFab.textContent = totalItems;
        if(totalItems > 0) {
            countElementFab.classList.add("visible");
        } else {
            countElementFab.classList.remove("visible");
        }
    }
}



// Expose cart control functions globally for inline onclick handlers used in the sidebar
window.updateItemQty = function(id, change) {
    let cart = JSON.parse(localStorage.getItem("cart")) || [];
    let item = cart.find(i => i.id === id);
    if (item) {
        item.quantity += change;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.id !== id);
        }
        localStorage.setItem("cart", JSON.stringify(cart));
        updateCartCounter();
        renderCartSidebar();
    }
}