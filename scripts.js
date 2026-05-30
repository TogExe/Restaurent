const App = (() => {
    // =========================================================================
    // 1. UTILITAIRES GLOBAUX
    // =========================================================================
    const qs = (selector, root = document) => root.querySelector(selector);
    const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const formatMoney = (value) => Number(value).toFixed(2).replace('.', ',') + ' €';

    const escapeHtml = (texte) => {
        if (!texte) return '';
        return texte.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    };

    const showSavedTip = (bouton, messageTexte = 'Enregistré') => {
        if (!bouton) return;
        const bulle = document.createElement('span');
        bulle.className = 'saved-tip';
        bulle.textContent = messageTexte;
        bouton.parentNode.appendChild(bulle);
        setTimeout(() => bulle.remove(), 1500);
    };

    // =========================================================================
    // 2. INITIALISATIONS UI (Bases)
    // =========================================================================
    const initConfirmForms = () => {
        qsa('form[data-confirm]:not([data-confirm-init])').forEach(form => {
            form.dataset.confirmInit = '1';
            form.addEventListener('submit', (e) => {
                if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    };

    const initTabs = () => {
        qsa('[data-tab-target]').forEach(button => {
            button.addEventListener('click', () => {
                qsa('[data-tab-target]').forEach(btn => btn.classList.remove('active'));
                qsa('.tab-panel').forEach(panel => panel.classList.remove('active'));
                
                const target = button.dataset.tabTarget;
                const panel = qs('#tab-' + target);
                if (panel) panel.classList.add('active');
                button.classList.add('active');
            });
        });
    };

    window.switchTab = (id, el) => {
        qsa('.tab-panel').forEach(panel => panel.classList.remove('active'));
        qsa('.tab-btn').forEach(button => button.classList.remove('active'));
        const targetPanel = qs('#tab-' + id);
        if (targetPanel) targetPanel.classList.add('active');
        if (el) el.classList.add('active');
    };

    const initLikeButtons = () => {
        qsa('.like-btn[data-action][data-id]').forEach(button => {
            button.addEventListener('click', async () => {
                const card = button.closest('li');
                const { action, id } = button.dataset;
                if (!card || !action || !id) return;

                const innerButtons = card.querySelectorAll('.like-btn');
                innerButtons.forEach(btn => btn.disabled = true);

                button.classList.remove('popping');
                void button.offsetWidth;
                button.classList.add('popping');
                button.addEventListener('animationend', () => button.classList.remove('popping'), { once: true });

                try {
                    const response = await fetch(`menu.php?action=${encodeURIComponent(action)}&id=${encodeURIComponent(id)}&ajax=1`);
                    if (!response.ok) throw new Error('Erreur réseau');
                    const data = await response.json();
                    
                    const likeCount = card.querySelector('.like-count');
                    const dislikeCount = card.querySelector('.dislike-count');
                    if (likeCount) likeCount.textContent = data.likes;
                    if (dislikeCount) dislikeCount.textContent = data.dislikes;
                } catch (error) {
                    console.error(error);
                } finally {
                    innerButtons.forEach(btn => btn.disabled = false);
                }
            });
        });
    };

    // =========================================================================
    // 3. PROFIL UTILISATEUR
    // =========================================================================
    const initProfileEditButtons = () => {
        qsa('.field-edit-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const input = qs('#' + btn.dataset.target);
                if (!input) return;

                if (input.readOnly) {
                    input.readOnly = false;
                    input.focus();
                    btn.textContent = '💾';
                } else {
                    input.readOnly = true;
                    btn.textContent = '✏️';
                    await saveProfileField(input, btn);
                }
            });
        });
    };

    const saveProfileField = async (input, btn) => {
        const fd = new FormData();
        fd.append('update_profile', '1');
        fd.append('ajax', '1');
        fd.append(input.name, input.value);

        try {
            const response = await fetch('profil.php', { method: 'POST', body: fd, credentials: 'same-origin' });
            const data = await response.json();

            if (data?.success) {
                showSavedTip(btn, 'Enregistré');
                if (data.address_parts) {
                    const a = data.address_parts;
                    const el = qs('.front-row-address');
                    if (el) {
                        el.innerHTML = '';
                        if (a.street?.trim() || a.number?.trim()) el.innerHTML += `<div>📍 ${(a.street || '')} ${(a.number || '')}</div>`;
                        if (a.complement?.trim()) el.innerHTML += `<div>🧾 ${a.complement}</div>`;
                        if (a.postal?.trim() || a.city?.trim()) el.innerHTML += `<div class="addr-line small">🏙 ${(a.postal || '')} ${(a.city || '')}</div>`;
                    }
                }
            }
        } catch (error) {
            console.error(error);
            showSavedTip(btn, 'Erreur');
        }
    };

    // =========================================================================
    // 4. MODULE MENU & PANIER
    // =========================================================================
    let menuCart = JSON.parse(localStorage.getItem('menuCart') || '[]');
    const saveMenuCart = () => localStorage.setItem('menuCart', JSON.stringify(menuCart));
    const findMenuCartItem = (id) => menuCart.find(item => item.id === id);

    const renderMenuCart = () => {
        const list = qs('#cart-items-list');
        const total = qs('#cart-total');
        const fabCount = qs('#cart-fab-count');
        const navCount = qs('#cart-count');
        const checkoutBtn = qs('#cart-checkout-btn');

        if (!list || !total || !fabCount) return;

        const totalItems = menuCart.reduce((sum, item) => sum + item.quantity, 0);
        const totalPrice = menuCart.reduce((sum, item) => sum + item.price * item.quantity, 0);

        if (navCount) {
            navCount.textContent = totalItems;
            navCount.style.display = totalItems > 0 ? 'inline-block' : 'none';
        }

        fabCount.textContent = totalItems;
        fabCount.classList.toggle('visible', totalItems > 0);
        total.textContent = totalPrice.toLocaleString('fr-FR', { minimumFractionDigits: 2 }) + ' €';

        if (checkoutBtn) {
            checkoutBtn.style.opacity = menuCart.length ? '1' : '0.4';
            checkoutBtn.style.pointerEvents = menuCart.length ? 'auto' : 'none';
        }

        if (menuCart.length === 0) {
            list.innerHTML = `<div class="cart-empty"><div class="cart-empty-icon">🛒</div><p>Votre panier est vide.<br>Ajoutez des plats depuis la carte !</p></div>`;
            return;
        }

        list.innerHTML = menuCart.map(item => {
            const subtotal = (item.price * item.quantity).toLocaleString('fr-FR', { minimumFractionDigits: 2 });
            return `
            <div class="cart-item" data-id="${item.id}">
                <div class="cart-item-info">
                    <div class="cart-item-name">${escapeHtml(item.name)}</div>
                    <div class="cart-item-price">${subtotal} €</div>
                </div>
                <div class="cart-item-qty">
                    <button class="qty-btn remove-btn" onclick="changeQty('${item.id}', -1)" title="Retirer un">−</button>
                    <span class="qty-value">${item.quantity}</span>
                    <button class="qty-btn" onclick="changeQty('${item.id}', 1)" title="Ajouter un">+</button>
                    <button class="qty-btn remove-btn" onclick="removeFromCart('${item.id}')" title="Supprimer" style="margin-left:4px;">🗑</button>
                </div>
            </div>`;
        }).join('');
    };

    window.addToCart = (id, name, price) => {
        if (!id) return;
        const item = findMenuCartItem(id);
        if (item) item.quantity += 1;
        else menuCart.push({ id, name: name || '', price: Number(price) || 0, quantity: 1 });
        saveMenuCart();
        renderMenuCart();
    };

    const changeMenuQty = (id, delta) => {
        const item = findMenuCartItem(id);
        if (!item) return;
        item.quantity += Number(delta) || 0;
        if (item.quantity <= 0) menuCart = menuCart.filter(i => i.id !== id);
        saveMenuCart();
        renderMenuCart();
    };

    window.removeFromCart = (id) => {
        menuCart = menuCart.filter(item => item.id !== id);
        saveMenuCart();
        renderMenuCart();
    };

    window.openCart = () => {
        qs('#cart-sidebar')?.classList.add('open');
        qs('#cart-overlay')?.classList.add('open');
    };

    window.closeCart = () => {
        qs('#cart-sidebar')?.classList.remove('open');
        qs('#cart-overlay')?.classList.remove('open');
    };

    const initMenuPage = () => {
        if (!qs('#cart-fab')) return;

        qsa('.add-to-cart-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                window.addToCart(btn.dataset.id, btn.dataset.name, btn.dataset.price);
                const original = btn.innerHTML;
                btn.innerHTML = '✅ Ajouté !';
                btn.classList.add('added');
                btn.style.pointerEvents = 'none';
                setTimeout(() => {
                    btn.innerHTML = original;
                    btn.classList.remove('added');
                    btn.style.pointerEvents = 'auto';
                }, 1100);
            });
        });

        qs('#cart-fab').addEventListener('click', window.openCart);
        qs('#cart-close-btn')?.addEventListener('click', window.closeCart);
        qs('#cart-overlay')?.addEventListener('click', window.closeCart);
        qs('#cart-clear-btn')?.addEventListener('click', () => {
            if (menuCart.length && window.confirm('Vider tout le panier ?')) {
                menuCart = [];
                saveMenuCart();
                renderMenuCart();
            }
        });
        renderMenuCart();
    };

    // =========================================================================
    // 5. MODULE COMMANDES COMPLET (commande.php & commande_simple.php)
    // =========================================================================
    
    // --- Commande Standard ---
    const initCommandePage = () => {
        let cart = {}, prices = {}, names = {};
        const elements = {
            items: qs('#cartItems'), totalDiv: qs('#cartTotal'), totalVal: qs('#totalVal'),
            orderBtn: qs('#orderBtn'), payAmt: qs('#payAmt'), payModal: qs('#payModal'),
            payBtn: qs('#payBtn'), payCancel: qs('#payCancel'), addr: {
                street: qs('#addr_street'), number: qs('#addr_number'), comp: qs('#addr_comp'),
                postal: qs('#addr_postal'), city: qs('#addr_city')
            }, card: {
                num: qs('#cardNum'), exp: qs('#cardExp'), name: qs('#cardName'), cvv: qs('#cardCvv')
            }, forms: {
                order: qs('#orderForm'), cartData: qs('#cartData'), addrData: qs('#addrData')
            }
        };

        if (!elements.items || !elements.orderBtn || !elements.payModal) return;

        const render = () => {
            let html = '', total = 0, count = 0;
            
            Object.keys(cart).forEach(id => {
                const qty = cart[id], price = Number(prices[id] || 0), name = names[id] || '';
                total += price * qty;
                count += qty;
                html += `<div class="cart-item"><span>${name} ×${qty}</span><span class="cart-item-price">${formatMoney(price * qty)}</span></div>`;
                const qtyLabel = qs(`#qty-${id}`);
                if (qtyLabel) qtyLabel.textContent = qty;
            });

            qsa('.qty-val').forEach(el => { if (!cart[el.id.replace('qty-', '')]) el.textContent = '0'; });
            
            elements.items.innerHTML = html || '<p class="cart-empty">Aucun article pour l\'instant.</p>';
            elements.totalDiv.classList.toggle('is-hidden', count === 0);
            elements.totalVal.textContent = formatMoney(total);
            elements.orderBtn.disabled = count === 0;
            elements.orderBtn.classList.toggle('order-btn-disabled', count === 0);
            if (elements.payAmt) elements.payAmt.textContent = formatMoney(total);
        };

        const changeQty = (id, price, name, delta) => {
            cart[id] = (cart[id] || 0) + delta;
            if (cart[id] <= 0) delete cart[id];
            prices[id] = price;
            names[id] = name;
            render();
        };

        qsa('.qty-btn[data-delta]').forEach(btn => {
            btn.addEventListener('click', () => {
                const { id, price, name, delta } = btn.dataset;
                if (id && delta) changeQty(id, Number(price), name, Number(delta));
            });
        });

        elements.orderBtn.addEventListener('click', () => {
            const { street, postal, city } = elements.addr;
            if (!street?.value.trim() || !postal?.value.trim() || !city?.value.trim()) {
                window.alert('Veuillez renseigner au moins la rue, le code postal et la ville.');
                return;
            }
            elements.payModal.classList.add('open');
        });

        elements.payCancel?.addEventListener('click', () => elements.payModal.classList.remove('open'));
        
        elements.card.num?.addEventListener('input', (e) => {
            const v = e.target.value.replace(/\D/g, '').substring(0, 16);
            e.target.value = v.match(/.{1,4}/g)?.join(' ') || v;
        });

        elements.card.exp?.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length >= 3) v = `${v.substring(0, 2)}/${v.substring(2, 4)}`;
            e.target.value = v;
        });

        elements.payBtn?.addEventListener('click', () => {
            const c = elements.card;
            if (!c.name.value.trim() || c.num.value.replace(/\s/g, '').length < 16 || c.exp.value.length < 5 || c.cvv.value.length < 3) {
                window.alert('Veuillez remplir tous les champs de paiement.');
                return;
            }

            elements.payBtn.textContent = '⏳ Traitement…';
            elements.payBtn.disabled = true;

            setTimeout(() => {
                elements.forms.cartData.value = JSON.stringify(cart);
                const a = elements.addr;
                elements.forms.addrData.value = [a.number.value, a.street.value, a.comp.value, a.postal.value, a.city.value].map(s => s.trim()).filter(Boolean).join(' ');
                elements.payModal.classList.remove('open');
                elements.forms.order.submit();
            }, 1800);
        });

        render();
    };

    // --- Commande Simple ---
    let orderCart = {}, orderPrices = {}, orderNames = {};
    const saveOrderCart = () => {
        const items = Object.keys(orderCart).map(id => ({ id: encodeURIComponent(id), name: orderNames[id], price: orderPrices[id], quantity: orderCart[id] }));
        localStorage.setItem('commandeCart', JSON.stringify(items));
    };

    const changeOrderQty = (id, price, name, delta) => {
        if (!id || !delta) return;
        orderCart[id] = (orderCart[id] || 0) + Number(delta);
        if (orderCart[id] <= 0) delete orderCart[id];
        orderPrices[id] = Number(price || orderPrices[id] || 0);
        orderNames[id] = name || orderNames[id] || '';
        saveOrderCart();
        renderOrderCart();
    };

    const renderOrderCart = () => {
        const container = qs('#cartItems'), totalDiv = qs('#cartTotal'), totalVal = qs('#totalVal'), orderBtn = qs('#orderBtn');
        if (!container || !totalDiv || !totalVal || !orderBtn) return;

        let html = '', total = 0, count = 0;
        Object.keys(orderCart).forEach(id => {
            const qty = orderCart[id], price = Number(orderPrices[id] || 0), name = orderNames[id] || '';
            total += price * qty;
            count += qty;
            html += `<div class="cart-item"><span>${escapeHtml(name)} ×${qty}</span><span class="cart-item-price">${(price * qty).toFixed(2).replace('.', ',')} €</span></div>`;
            const qtyLabel = qs(`#qty-${id}`);
            if (qtyLabel) qtyLabel.textContent = qty;
        });

        qsa('.qty-val').forEach(el => { if (!orderCart[el.id.replace('qty-', '')]) el.textContent = '0'; });
        container.innerHTML = html || '<p class="cart-empty">Aucun article pour l\'instant.</p>';
        totalDiv.classList.toggle('is-hidden', count === 0);
        totalVal.textContent = `${total.toFixed(2).replace('.', ',')} €`;
        orderBtn.disabled = count === 0;
        orderBtn.classList.toggle('order-btn-disabled', count === 0);
    };

    const initCommandeSimplePage = () => {
        if (!qs('#cartItems')) return;
        
        JSON.parse(localStorage.getItem('commandeCart') || localStorage.getItem('cart') || '[]').forEach(item => {
            const id = decodeURIComponent(item.id);
            orderCart[id] = item.quantity;
            orderPrices[id] = item.price;
            orderNames[id] = item.name;
        });

        window.openPayment = () => {
            const addr = qs('#deliveryAddr')?.value.trim();
            if (!addr) return window.alert('Veuillez entrer une adresse de livraison.');
            
            qs('#cartData').value = JSON.stringify(orderCart);
            qs('#addrData').value = addr;
            localStorage.removeItem('commandeCart');
            
            const btn = qs('#orderBtn');
            if (btn) { btn.textContent = '⏳ Redirection...'; btn.disabled = true; }
            qs('#orderForm').submit();
        };
        renderOrderCart();
    };

    // Fonction de routage globale pour gérer les 2 types de paniers depuis le HTML
    window.changeQty = (id, a, b, c) => (typeof b === 'undefined') ? changeMenuQty(id, a) : changeOrderQty(id, a, b, c);


    // =========================================================================
    // 6. MES COMMANDES (Client)
    // =========================================================================
    let additionsState = {};

    window.toggleAdditions = (orderId) => {
        const panel = qs(`#additions-panel-${orderId}`);
        if (!panel) return;
        panel.style.display = (panel.style.display === 'none' || panel.style.display === '') ? 'block' : 'none';
        if (!additionsState[orderId]) additionsState[orderId] = {};
        window.updateAdditionTotal(orderId);
    };

    window.adjustAdditionQty = (orderId, dishId, delta) => {
        if (!additionsState[orderId]) additionsState[orderId] = {};
        
        let newQty = (additionsState[orderId][dishId] || 0) + delta;
        if (newQty <= 0) delete additionsState[orderId][dishId];
        else additionsState[orderId][dishId] = newQty;

        const qtyLabel = qs(`#qty-add-${orderId}-${dishId}`);
        if (qtyLabel) qtyLabel.textContent = Math.max(0, newQty);
        window.updateAdditionTotal(orderId);
    };

    window.updateAdditionTotal = (orderId) => {
        const panel = qs(`#additions-panel-${orderId}`);
        if (!panel) return;

        let total = 0;
        panel.querySelectorAll('.addition-item').forEach(item => {
            const pid = item.dataset.pid;
            const price = parseFloat(item.dataset.price) || 0;
            total += price * ((additionsState[orderId] || {})[pid] || 0);
        });

        const totalSpan = qs(`#diff-val-${orderId}`);
        if (totalSpan) totalSpan.textContent = total.toFixed(2).replace('.', ',') + ' €';

        const btn = qs(`#checkout-add-btn-${orderId}`);
        if (btn) btn.disabled = total <= 0;
        
        const dataInput = qs(`#additions-data-${orderId}`);
        if (dataInput) dataInput.value = JSON.stringify(additionsState[orderId] || {});
    };

    const initMesCommandesAjax = () => {
        const listeCommandes = qs('.orders-list');
        if (!listeCommandes) return;

        listeCommandes.addEventListener('submit', async (e) => {
            if (e.defaultPrevented || !e.target.classList.contains('ajax-cancel-order-form')) return;
            e.preventDefault();

            const form = e.target;
            const fd = new FormData(form);
            fd.append('cancel_order', '1');
            fd.append('ajax', '1');

            const carte = form.closest('.order-card');
            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;

            try {
                const response = await fetch('mes_commandes.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await response.json();

                if (data.success) {
                    carte.style.transition = 'all 0.5s ease';
                    carte.style.opacity = '0';
                    carte.style.transform = 'translateY(15px) scale(0.95)';
                    setTimeout(() => {
                        carte.remove();
                        if (listeCommandes.querySelectorAll('.order-card').length === 0) {
                            listeCommandes.innerHTML = `<div class="glass-panel" style="text-align: center; padding: 40px;"><p style="color: var(--text-muted); margin-bottom: 20px; font-size: 1.1rem;">Vous n'avez pas encore passé de commande.</p><a href="commande.php" class="btn" style="text-decoration: none;">Passer ma première commande →</a></div>`;
                        }
                    }, 500);
                } else {
                    alert(data.message || "Erreur d'annulation.");
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                if (btn) btn.disabled = false;
            }
        });
    };


    // =========================================================================
    // 7. ROLES (Admin, Cuisinier, Livreur)
    // =========================================================================
    
    // --- Admin ---
    const initAdminAjax = () => {
        qs('#tab-users')?.addEventListener('submit', async (e) => {
            if (e.defaultPrevented) return;
            const form = e.target;
            let action = e.submitter?.name || ['delete_user', 'ban_user', 'unban_user', 'change_role'].find(n => form.querySelector(`button[name="${n}"]`));
            if (!['change_role', 'delete_user', 'ban_user', 'unban_user'].includes(action)) return;

            e.preventDefault();
            const fd = new FormData(form);
            fd.append(action, '1');
            fd.append('ajax', '1');

            const tr = form.closest('tr');
            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('admin.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                
                if (data.success) {
                    if (action === 'delete_user') {
                        tr.style.transition = 'all 0.5s ease';
                        tr.style.opacity = '0';
                        tr.style.transform = 'translateX(-20px)';
                        setTimeout(() => tr.remove(), 500);
                    } else if (action === 'change_role') {
                        const pill = tr.querySelector('.role-pill');
                        if (pill) {
                            pill.style.color = pill.style.borderColor = data.color;
                            pill.innerHTML = `${data.icon} ${data.role}`;
                        }
                        showSavedTip(btn, 'Mis à jour');
                    } else if (action === 'ban_user' || action === 'unban_user') {
                        form.dataset.confirm = data.is_banned ? "Débannir cet utilisateur ?" : "Bannir cet utilisateur ?";
                        btn.name = data.is_banned ? "unban_user" : "ban_user";
                        btn.className = data.is_banned ? "btn-success-sm" : "btn-danger-sm";
                        btn.textContent = data.is_banned ? "✅" : "🚫";
                        showSavedTip(btn, data.is_banned ? 'Banni' : 'Débanni');
                    }
                } else alert(data.message || 'Erreur.');
            } catch (err) { console.error(err); } 
            finally { if (btn) btn.disabled = false; }
        });

        qs('#tab-dishes')?.addEventListener('submit', async (e) => {
            if (e.defaultPrevented) return;
            const form = e.target;
            const isDelete = e.submitter?.name === 'delete_dish' || form.querySelector('button[name="delete_dish"]');
            const isAdd = e.submitter?.name === 'add_dish' || form.querySelector('button[name="add_dish"]');
            
            if (!isDelete && !isAdd) return;
            e.preventDefault();
            
            const fd = new FormData(form);
            fd.append(isDelete ? 'delete_dish' : 'add_dish', '1');
            fd.append('ajax', '1');

            const btn = form.querySelector('button');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch('admin.php', { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                
                if (data.success) {
                    if (isDelete) {
                        const tr = form.closest('tr');
                        tr.style.transition = 'all 0.5s ease';
                        tr.style.opacity = '0';
                        setTimeout(() => tr.remove(), 500);
                    } else if (isAdd) {
                        form.reset();
                        showSavedTip(btn, 'Plat ajouté !');
                        const tbody = qs('#tab-dishes tbody');
                        if (tbody) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `<td style="font-weight:600;color:var(--sapphire);">${escapeHtml(data.dish.name)}</td><td style="color:var(--softlime);">${Number(data.dish.price).toFixed(2).replace('.', ',')} €</td><td>${data.dish.is_vegetarian ? '🌱' : '—'}</td><td style="color:var(--text-muted);">👍 0</td><td><form method="POST" onsubmit="return confirm('Supprimer ce plat ?');" style="display:inline;"><input type="hidden" name="dish_id" value="${escapeHtml(data.dish.id)}"><button type="submit" name="delete_dish" class="btn-danger-sm">🗑</button></form></td>`;
                            tr.style.opacity = '0';
                            tbody.appendChild(tr);
                            void tr.offsetWidth;
                            tr.style.transition = 'all 0.5s ease';
                            tr.style.opacity = '1';
                        }
                    }
                } else alert(data.message || 'Erreur.');
            } catch (err) { console.error(err); }
            finally { if (btn) btn.disabled = false; }
        });
    };

    // --- Cuisinier & Livreur (Logique commune factorisée) ---
    const initRoleAjax = (scriptName, statusKey, transitionMap) => {
        const container = qs('.orders');
        if (!container) return;

        container.addEventListener('submit', async (e) => {
            if (e.defaultPrevented) return;
            const form = e.target;
            if (e.submitter?.name !== 'change_status' && !form.querySelector('button[name="change_status"]')) return;
            
            e.preventDefault();
            const fd = new FormData(form);
            fd.append('change_status', '1');
            fd.append('ajax', '1');

            const card = form.closest('.order-card');
            const btn = form.querySelector('button[name="change_status"]');
            if (btn) btn.disabled = true;

            try {
                const res = await fetch(form.action || scriptName, { method: 'POST', body: fd, credentials: 'same-origin' });
                const data = await res.json();
                
                if (data.success) {
                    const filter = qs('.filter-select')?.value || 'all';
                    const config = transitionMap[data.ready];
                    
                    if (config.hideOn.includes(filter)) {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '0';
                        card.style.transform = 'scale(0.9)';
                        setTimeout(() => {
                            card.remove();
                            if (!container.querySelector('.order-card')) container.innerHTML = `<p class='empty-orders'>Aucune commande.</p>`;
                        }, 500);
                    } else {
                        if (btn) btn.disabled = false;
                        const statusTxt = card.querySelector(config.selector);
                        if (statusTxt) {
                            statusTxt.className = config.cssClass;
                            statusTxt.textContent = config.label;
                        }
                        if (config.btnUpdate) config.btnUpdate(form, btn);
                    }
                } else {
                    alert('Erreur.');
                    if (btn) btn.disabled = false;
                }
            } catch (err) {
                console.error(err);
                if (btn) btn.disabled = false;
            }
        });
    };

    // Configuration des états pour le cuisinier
    const initCuisineAjax = () => initRoleAjax('cuisinier.php', 'ready', {
        1: { hideOn: ['paid', 'prepared'], selector: '.status-paid, .status-progress', cssClass: 'status-progress', label: 'En préparation', btnUpdate: (f, b) => { if(b) b.textContent = 'Définir comme Prête'; } },
        2: { hideOn: ['paid', 'in-progress'], selector: '.status-progress', cssClass: 'status-ready', label: 'Prête', btnUpdate: (f) => f.outerHTML = `<div class='order-ready-badge'>✓ Commande Prête</div>` }
    });

    // Configuration des états pour le livreur
    const initLivreurAjax = () => initRoleAjax('livreur.php', 'ready', {
        3: { hideOn: ['to-pickup', 'delivered'], selector: '.delivery-status-ready, .delivery-status-transit, .delivery-status-default', cssClass: 'delivery-status-transit', label: 'En livraison', btnUpdate: (f, b) => { if(b) { b.textContent = 'Marquer comme Livrée'; b.className = 'btn delivery-btn-finished'; } } },
        4: { hideOn: ['to-pickup', 'in-transit'], selector: '.delivery-status-transit', cssClass: 'delivery-status-done', label: 'Livrée ✅', btnUpdate: (f) => f.outerHTML = `<div class='btn delivery-complete-badge'>Livraison Terminée ✅</div>` }
    });

    const initAutoSubmitCybankForm = () => qs('#cybankForm')?.submit();

    // =========================================================================
    // INITIALISATION PRINCIPALE
    // =========================================================================
    return {
        init: () => {
            initConfirmForms(); initTabs(); initLikeButtons(); initCommandePage();
            initCommandeSimplePage(); initMenuPage(); initProfileEditButtons();
            initAutoSubmitCybankForm(); initAdminAjax(); initCuisineAjax();
            initLivreurAjax(); initMesCommandesAjax();
        }
    };
})();

document.addEventListener('DOMContentLoaded', () => App.init());