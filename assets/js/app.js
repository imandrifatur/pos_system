// ============================================================
// POS SYSTEM — MAIN JAVASCRIPT
// ============================================================

function toggleSidebar() {
  const sidebar = document.getElementById('sidebar');
  const main = document.querySelector('.main-content');
  sidebar.classList.toggle('collapsed');
  main && main.classList.toggle('expanded');
}

// Auto-hide alerts
document.querySelectorAll('.alert').forEach(alert => {
  setTimeout(() => alert.remove(), 5000);
});

// ===================== MODAL HELPERS =====================
function openModal(id) {
  document.getElementById(id)?.classList.add('open');
}
function closeModal(id) {
  document.getElementById(id)?.classList.remove('open');
}
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', function(e) {
    if (e.target === this) this.classList.remove('open');
  });
});

// ===================== NUMBER FORMAT =====================
function formatRupiah(num) {
  return 'Rp ' + Number(num).toLocaleString('id-ID');
}

// ===================== POS KASIR =====================
let cart = [];

function addToCart(id, name, price, stock) {
  const existing = cart.find(item => item.id === id);
  if (existing) {
    if (existing.qty >= stock) return showToast('Stok tidak mencukupi', 'error');
    existing.qty++;
  } else {
    cart.push({ id, name, price, stock, qty: 1, discount: 0 });
  }
  renderCart();
  showToast(name + ' ditambahkan', 'success');
}

function updateQty(index, delta) {
  cart[index].qty += delta;
  if (cart[index].qty <= 0) cart.splice(index, 1);
  renderCart();
}

function removeFromCart(index) {
  cart.splice(index, 1);
  renderCart();
}

function renderCart() {
  const container = document.getElementById('cart-items');
  const subtotalEl = document.getElementById('cart-subtotal');
  const totalEl = document.getElementById('cart-total');
  const countEl = document.getElementById('cart-count');
  if (!container) return;

  if (cart.length === 0) {
    container.innerHTML = '<div class="empty-state"><i class="fas fa-shopping-cart"></i><p>Keranjang kosong</p></div>';
    if (subtotalEl) subtotalEl.textContent = 'Rp 0';
    if (totalEl) totalEl.textContent = 'Rp 0';
    if (countEl) countEl.textContent = '0';
    return;
  }

  let subtotal = 0;
  let html = '';
  cart.forEach((item, i) => {
    const itemTotal = item.price * item.qty;
    subtotal += itemTotal;
    html += `
    <div class="cart-item">
      <div class="flex-1">
        <div class="cart-item-name">${item.name}</div>
        <div class="cart-item-price">${formatRupiah(item.price)} × ${item.qty} = ${formatRupiah(itemTotal)}</div>
      </div>
      <div class="cart-qty-ctrl">
        <button class="qty-btn" onclick="updateQty(${i},-1)"><i class="fas fa-minus"></i></button>
        <span class="qty-value">${item.qty}</span>
        <button class="qty-btn" onclick="updateQty(${i},1)"><i class="fas fa-plus"></i></button>
        <button class="qty-btn" onclick="removeFromCart(${i})" style="color:var(--red)"><i class="fas fa-trash"></i></button>
      </div>
    </div>`;
  });

  container.innerHTML = html;
  if (subtotalEl) subtotalEl.textContent = formatRupiah(subtotal);
  if (totalEl) totalEl.textContent = formatRupiah(subtotal);
  if (countEl) countEl.textContent = cart.length;

  // Update hidden input
  const cartInput = document.getElementById('cart-data');
  if (cartInput) cartInput.value = JSON.stringify(cart);
}

function filterProducts(q) {
  document.querySelectorAll('.product-card').forEach(card => {
    const name = card.dataset.name?.toLowerCase() || '';
    card.style.display = name.includes(q.toLowerCase()) ? '' : 'none';
  });
}

function calculateChange() {
  const totalEl = document.getElementById('cart-total');
  const paidEl  = document.getElementById('paid-amount');
  const changeEl = document.getElementById('change-due');
  if (!totalEl || !paidEl || !changeEl) return;
  const total = parseInt(totalEl.textContent.replace(/\D/g,'')) || 0;
  const paid  = parseInt(paidEl.value.replace(/\D/g,'')) || 0;
  changeEl.textContent = formatRupiah(Math.max(0, paid - total));
}

// ===================== TOAST =====================
function showToast(message, type = 'success') {
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="fas fa-${type==='success'?'check':'exclamation'}-circle"></i> ${message}`;
  toast.style.cssText = `position:fixed;bottom:20px;right:20px;background:var(--bg-elevated);border:1px solid var(--border);padding:12px 18px;border-radius:8px;font-size:13px;z-index:9999;display:flex;gap:8px;align-items:center;color:${type==='success'?'var(--green)':'var(--red)'};box-shadow:var(--shadow);animation:slideIn .2s ease;`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 3000);
}

// ===================== CONFIRM DELETE =====================
document.querySelectorAll('[data-confirm]').forEach(btn => {
  btn.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Yakin ingin menghapus?')) e.preventDefault();
  });
});
