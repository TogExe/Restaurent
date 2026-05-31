document.addEventListener("DOMContentLoaded", () => {
    initThemeSwitcher();
    initCartSystem();
});

/* =========================================
   1. GESTION DU THÈME
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
   2. UNIFIED CART SYSTEM
========================================= */
function initCartSystem() {
    updateCartCounter();

    // Elements
    const sidebar = document.getElementById("cart-sidebar");
    const overlay = document.getElementById("cart-overlay");
    const closeBtn = document.getElementById("cart-close-btn");
    const fabBtn = document.getElementById("cart-fab");
    const addToCartBtns = document.querySelectorAll(".add-to-cart-btn");

    // Toggles
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

    // Add Item logic
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

function renderCartSidebar() {
    const cartList = document.getElementById("cart-items-list");
    const cartTotal = document.getElementById("cart-total");
    if(!cartList || !cartTotal) return;

    const cart = JSON.parse(localStorage.getItem("cart")) || [];
    
    if (cart.length === 0) {
        cartList.innerHTML = `<div class="cart-empty"><div class="cart-empty-icon">🛒</div><p>Votre panier est vide.<br>Ajoutez des plats depuis la carte !</p></div>`;
        cartTotal.textContent = "0,00 €";
        return;
    }

    cartList.innerHTML = "";
    let total = 0;

    cart.forEach(item => {
        total += (item.price * item.quantity);
        cartList.innerHTML += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">${item.price.toFixed(2).replace('.', ',')} €</div>
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn" onclick="updateItemQty('${item.id}', -1)">-</button>
                    <span class="qty-value">${item.quantity}</span>
                    <button class="qty-btn" onclick="updateItemQty('${item.id}', 1)">+</button>
                </div>
            </div>
        `;
    });

    cartTotal.textContent = total.toFixed(2).replace('.', ',') + " €";
}

// Global functions so inline onclick handlers in renderCartSidebar work
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