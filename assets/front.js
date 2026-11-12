document.addEventListener('DOMContentLoaded', () => {
 document.querySelectorAll('.pov-configurator').forEach(root => {
  const form = root.closest('form.cart');
  if (!form) return;
  form.enctype = 'multipart/form-data';
  const bubble = document.querySelector('.pov-price-bubble');
  const getQty = () => {
   const fields = [...form.querySelectorAll('input.qty')];
   const field = fields.find(el => el.getClientRects().length && !el.disabled) || fields.find(el => !el.disabled);
   return field || null;
  };
  let tiers = {};
  try { tiers = JSON.parse(root.dataset.tiers || '{}'); } catch (_) {}
  const money = value => {
   const n = Number.isFinite(value) ? Math.max(0, value) : 0;
   try { return new Intl.NumberFormat(povConfig.locale, {style:'currency', currency:povConfig.currency, minimumFractionDigits:Number(povConfig.decimals)}).format(n); }
   catch (_) { return n.toFixed(2); }
  };
  const compute = () => {
   const input = getQty();
   const rawQty = Number(input?.value);
   const q = Number.isFinite(rawQty) && rawQty > 0 ? Math.floor(rawQty) : Math.max(1, Number(root.dataset.min) || 1);
   let base = Number(root.dataset.base) || 0, unit = 0, order = 0;
   root.querySelectorAll('.pov-group:not(.pov-artwork) input:checked').forEach(i => {
    const extra = Number(i.dataset.price) || 0;
    if (i.dataset.charge === 'order') order += extra; else unit += extra;
   });
   if (root.querySelector('[name="pov_artwork_choice"]:checked')?.value === 'service') order += Number(root.dataset.designFee) || 0;
   if (root.dataset.mode === 'dynamic') {
    Object.keys(tiers).map(Number).filter(t => t > 0).sort((a,b) => a-b).forEach(t => { if (q >= t) base = (Number(tiers[t]) || 0) / t; });
   }
   const raw = (base + unit) * q + order;
   const rate = Number(root.dataset.taxRate) || 0;
   const total = root.dataset.pricesTaxIncluded === '1' ? raw : raw * (1 + rate);
   if (bubble) {
    const price = bubble.querySelector('.pov-bubble-price');
    const perUnit = bubble.querySelector('.pov-bubble-unit');
    const quantity = bubble.querySelector('.pov-bubble-qty');
    if (price) price.textContent = money(total);
    if (perUnit) perUnit.textContent = money(total / q);
    if (quantity) quantity.textContent = new Intl.NumberFormat(povConfig.locale).format(q) + ' buc.';
   }
  };
  let scheduled = 0;
  const update = () => { cancelAnimationFrame(scheduled); scheduled = requestAnimationFrame(compute); };
  const updateArtwork = () => {
   const panel = root.querySelector('.pov-upload-panel');
   if (panel) panel.hidden = root.querySelector('[name="pov_artwork_choice"]:checked')?.value !== 'own';
   update();
  };
  root.addEventListener('change', updateArtwork);
  form.addEventListener('input', e => { if (e.target.matches('input.qty')) update(); });
  form.addEventListener('change', e => { if (e.target.matches('input.qty')) update(); });
  // Woodmart can set quantity after the click event: settle against the actual field value.
  form.addEventListener('click', e => {
   if (!e.target.closest('.plus,.minus,[data-quantity]')) return;
   update();
   setTimeout(update, 50);
   setTimeout(update, 250);
   setTimeout(update, 650);
  });
  let previous = '';
  const observer = setInterval(() => {
   if (!root.isConnected) { clearInterval(observer); return; }
   const value = getQty()?.value || '';
   if (value !== previous) { previous = value; update(); }
  }, 150);
  updateArtwork();
 });
});
