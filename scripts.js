let App = (function() {
    function qs(selector, root) {
        root = root || document;
        return root.querySelector(selector);
    }

    function qsa(selector, root) {
        root = root || document;
        let list = root.querySelectorAll(selector);
        return Array.prototype.slice.call(list);
    }

    function formatMoney(value) {
        return Number(value).toFixed(2).replace('.', ',') + ' €';
    }

    function initConfirmForms() {
        let forms = qsa('form[data-confirm]');
        for (let i = 0; i < forms.length; i++) {
            let form = forms[i];
            if (form.dataset.confirmInit) {
                continue;
            }
            form.dataset.confirmInit = '1';
            form.addEventListener('submit', function(event) {
                let message = this.dataset.confirm;
                if (message && !window.confirm(message)) {
                    event.preventDefault();
                }
            });
        }
    }

    function initTabs() {
        let buttons = qsa('[data-tab-target]');
        for (let i = 0; i < buttons.length; i++) {
            (function(button) {
                button.addEventListener('click', function() {
                    let allButtons = qsa('[data-tab-target]');
                    for (let j = 0; j < allButtons.length; j++) {
                        allButtons[j].classList.remove('active');
                    }
                    let panels = qsa('.tab-panel');
                    for (let k = 0; k < panels.length; k++) {
                        panels[k].classList.remove('active');
                    }
                    let target = button.dataset.tabTarget;
                    let panel = qs('#tab-' + target);
                    if (panel) {
                        panel.classList.add('active');
                    }
                    button.classList.add('active');
                });
            })(buttons[i]);
        }
    }

    function initLikeButtons() {
        let buttons = qsa('.like-btn[data-action][data-id]');
        for (let i = 0; i < buttons.length; i++) {
            (function(button) {
                button.addEventListener('click', function() {
                    let card = button.closest('li');
                    let action = button.dataset.action;
                    let id = button.dataset.id;
                    if (!card || !action || !id) {
                        return;
                    }

                    let innerButtons = card.querySelectorAll('.like-btn');
                    for (let j = 0; j < innerButtons.length; j++) {
                        innerButtons[j].disabled = true;
                    }

                    button.classList.remove('popping');
                    void button.offsetWidth;
                    button.classList.add('popping');
                    button.addEventListener('animationend', function() {
                        button.classList.remove('popping');
                    }, { once: true });

                    fetch('menu.php?action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(id) + '&ajax=1')
                        .then(function(response) {
                            if (!response.ok) {
                                throw new Error('Erreur réseau');
                            }
                            return response.json();
                        })
                        .then(function(data) {
                            let likeCount = card.querySelector('.like-count');
                            let dislikeCount = card.querySelector('.dislike-count');
                            if (likeCount) {
                                likeCount.textContent = data.likes;
                            }
                            if (dislikeCount) {
                                dislikeCount.textContent = data.dislikes;
                            }
                        })
                        .catch(function(error) {
                            console.error(error);
                        })
                        .then(function() {
                            for (let k = 0; k < innerButtons.length; k++) {
                                innerButtons[k].disabled = false;
                            }
                        });
                });
            })(buttons[i]);
        }
    }

    function initCommandePage() {
        let cart = {};
        let prices = {};
        let names = {};
        let cartItems = qs('#cartItems');
        let cartTotal = qs('#cartTotal');
        let totalVal = qs('#totalVal');
        let orderBtn = qs('#orderBtn');
        let payAmt = qs('#payAmt');
        let addrStreet = qs('#addr_street');
        let addrNumber = qs('#addr_number');
        let addrComp = qs('#addr_comp');
        let addrPostal = qs('#addr_postal');
        let addrCity = qs('#addr_city');
        let payModal = qs('#payModal');
        let payBtn = qs('#payBtn');
        let payCancel = qs('#payCancel');
        let cardNum = qs('#cardNum');
        let cardExp = qs('#cardExp');
        let orderForm = qs('#orderForm');
        let cartData = qs('#cartData');
        let addrData = qs('#addrData');
        let cardName = qs('#cardName');
        let cardCvv = qs('#cardCvv');

        if (!cartItems || !orderBtn || !payModal) {
            return;
        }

        function renderCart() {
            let html = '';
            let total = 0;
            let count = 0;
            let ids = Object.keys(cart);

            for (let i = 0; i < ids.length; i++) {
                let id = ids[i];
                let quantity = cart[id];
                let price = Number(prices[id] || 0);
                let name = names[id] || '';
                total += price * quantity;
                count += quantity;
                html += '<div class="cart-item"><span>' + name + ' ×' + quantity + '</span><span class="cart-item-price">' + formatMoney(price * quantity) + '</span></div>';
                let qtyLabel = qs('#qty-' + id);
                if (qtyLabel) {
                    qtyLabel.textContent = quantity;
                }
            }

            let qtyEls = qsa('.qty-val');
            for (let j = 0; j < qtyEls.length; j++) {
                let el = qtyEls[j];
                let pid = el.id.replace('qty-', '');
                if (!cart[pid]) {
                    el.textContent = '0';
                }
            }

            cartItems.innerHTML = html || '<p class="cart-empty">Aucun article pour l\'instant.</p>';
            cartTotal.classList.toggle('is-hidden', count === 0);
            totalVal.textContent = formatMoney(total);
            orderBtn.disabled = count === 0;
            orderBtn.classList.toggle('order-btn-disabled', count === 0);
            if (payAmt) {
                payAmt.textContent = formatMoney(total);
            }
        }

        function changeQty(id, price, name, delta) {
            cart[id] = (cart[id] || 0) + delta;
            if (cart[id] <= 0) {
                delete cart[id];
            }
            prices[id] = price;
            names[id] = name;
            renderCart();
        }

        let qtyButtons = qsa('.qty-btn[data-delta]');
        for (let m = 0; m < qtyButtons.length; m++) {
            (function(button) {
                button.addEventListener('click', function() {
                    let id = button.dataset.id;
                    let price = Number(button.dataset.price || 0);
                    let name = button.dataset.name || '';
                    let delta = Number(button.dataset.delta || 0);
                    if (!id || !delta) {
                        return;
                    }
                    changeQty(id, price, name, delta);
                });
            })(qtyButtons[m]);
        }

        function openPayment() {
            if (!addrStreet || !addrPostal || !addrCity) {
                return;
            }
            if (!addrStreet.value.trim() || !addrPostal.value.trim() || !addrCity.value.trim()) {
                window.alert('Veuillez renseigner au moins la rue, le code postal et la ville.');
                return;
            }
            payModal.classList.add('open');
        }

        function closeModal() {
            payModal.classList.remove('open');
        }

        function formatCardInput(el) {
            if (!el) {
                return;
            }
            let value = el.value.replace(/\D/g, '').substring(0, 16);
            let parts = value.match(/.{1,4}/g);
            el.value = parts ? parts.join(' ') : value;
        }

        function formatExpiryInput(el) {
            if (!el) {
                return;
            }
            let value = el.value.replace(/\D/g, '');
            if (value.length >= 3) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            el.value = value;
        }

        function submitPayment() {
            if (!cardName || !cardNum || !cardExp || !cardCvv || !orderForm || !cartData || !addrData) {
                return;
            }
            let name = cardName.value.trim();
            let num = cardNum.value.replace(/\s/g, '');
            let exp = cardExp.value;
            let cvv = cardCvv.value;

            if (!name || num.length < 16 || exp.length < 5 || cvv.length < 3) {
                window.alert('Veuillez remplir tous les champs de paiement.');
                return;
            }

            payBtn.textContent = '⏳ Traitement…';
            payBtn.disabled = true;

            setTimeout(function() {
                cartData.value = JSON.stringify(cart);
                
                // Reconstituer l'adresse pour le stockage dans commandes.json
                let fullAddr = [addrNumber.value, addrStreet.value, addrComp.value, addrPostal.value, addrCity.value]
                    .map(s => s.trim()).filter(s => s !== '').join(' ');
                addrData.value = fullAddr;
                
                closeModal();
                orderForm.submit();
            }, 1800);
        }

        if (orderBtn) {
            orderBtn.type = 'button';
            orderBtn.addEventListener('click', openPayment);
        }
        if (payBtn) {
            payBtn.type = 'button';
            payBtn.addEventListener('click', submitPayment);
        }
        if (payCancel) {
            payCancel.type = 'button';
            payCancel.addEventListener('click', closeModal);
        }
        if (cardNum) {
            cardNum.addEventListener('input', function() {
                formatCardInput(cardNum);
            });
        }
        if (cardExp) {
            cardExp.addEventListener('input', function() {
                formatExpiryInput(cardExp);
            });
        }

        renderCart();
    }

    function initProfileEditButtons() {
        var buttons = qsa('.field-edit-btn');
        for (var i = 0; i < buttons.length; i++) {
            (function(btn) {
                btn.addEventListener('click', function() {
                    var targetId = btn.dataset.target;
                    if (!targetId) { return; }
                    var input = qs('#' + targetId);
                    if (!input) { return; }
                    if (input.readOnly) {
                        input.readOnly = false;
                        input.focus();
                        btn.textContent = '💾';
                    } else {
                        // Save via AJAX without reloading
                        input.readOnly = true;
                        btn.textContent = '✏️';
                        saveProfileField(input, btn);
                    }
                });
            })(buttons[i]);
        }
    }

    function saveProfileField(input, btn) {
        var fd = new FormData();
        fd.append('update_profile', '1');
        fd.append('ajax', '1');
        fd.append(input.name, input.value);

        fetch('profil.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        }).then(function(resp) {
            return resp.json();
        }).then(function(data) {
            if (data && data.success) {
                // show saved indicator
                var tip = document.createElement('span');
                tip.className = 'saved-tip';
                tip.textContent = 'Enregistré';
                btn.parentNode.appendChild(tip);
                setTimeout(function() { tip.remove(); }, 1500);

                // update front-row address display if provided
                if (data.address_parts) {
                    var a = data.address_parts;
                    var el = qs('.front-row-address');
                    if (el) {
                        var line1 = (a.street || '') + ' ' + (a.number || '');
                        var line2 = a.complement || '';
                        var line3 = (a.postal || '') + ' ' + (a.city || '');
                        el.innerHTML = '';
                        if (line1.trim()) el.appendChild(document.createElement('div')).textContent = '📍 ' + line1.trim();
                        if (line2.trim()) el.appendChild(document.createElement('div')).textContent = '🧾 ' + line2.trim();
                        if (line3.trim()) { var d = document.createElement('div'); d.className='addr-line small'; d.textContent = '🏙 ' + line3.trim(); el.appendChild(d); }
                    }
                }
            }
        }).catch(function(err) {
            console.error('Erreur sauvegarde profil', err);
            var tip = document.createElement('span');
            tip.className = 'saved-tip error';
            tip.textContent = 'Erreur';
            btn.parentNode.appendChild(tip);
            setTimeout(function() { tip.remove(); }, 2000);
        });
    }

    return {
        init: function() {
            initConfirmForms();
            initTabs();
            initLikeButtons();
            initCommandePage();
            initProfileEditButtons();
        }
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    App.init();
});
