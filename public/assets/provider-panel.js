'use strict';
(() => {
  const ctx = document.querySelector('meta[name="panel-context"]').content;
  const screen = document.querySelector('meta[name="panel-screen"]').content;
  const base = `/api/v1/${ctx}/provider-panel/`;
  const $ = id => document.getElementById(id);
  let csrf = '', customer = '', activeId = null, offset = 0;
  const words = { REGISTER: 'Alan adı kaydı', RENEW: 'Alan adı yenilemesi', CREATE: 'VM oluşturma', START: 'Başlatma', STOP: 'Durdurma', RESTART: 'Yeniden başlatma', REINSTALL: 'Yeniden kurulum', TERMINATE: 'Kalıcı sonlandırma', PENDING: 'Sırada', WAITING_TASK: 'Sağlayıcı görevi bekleniyor', RUNNING: 'Çalışıyor', POLLING: 'Görev inceleniyor', UNKNOWN: 'Fiziksel sonuç belirsiz', MANUAL_REVIEW: 'Personel incelemesi gerekli', ACTIVE: 'Aktif', STOPPED: 'Durdurulmuş VM', SUSPENDED: 'Hizmet askıda', TERMINATED: 'Sonlandırma doğrulandı', REGISTERED: 'Kayıt doğrulandı', REGISTERING: 'Kayıt sürüyor', RENEWING: 'Yenileme sürüyor', SUCCEEDED: 'Kanıt doğrulandı', COSTED_MOCK: 'Mock maliyet kaydedildi', UNVERIFIED: 'Kanıt eksik; sıfır gider değildir', REVIEW: 'İnceleme gerekli', MATCHED_MOCK: 'Mock eşlik; ödeme değildir' };
  const text = value => words[value] || String(value ?? '—');
  function el(tag, value, className) { const e = document.createElement(tag); if (value !== undefined) e.textContent = text(value); if (className) e.className = className; return e; }
  function notice(value) { $('notice').textContent = value; }
  async function api(path, body, key) {
    const options = { credentials: 'same-origin', cache: 'no-store' };
    if (body !== undefined) { options.method = 'POST'; options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Idempotency-Key': key || crypto.randomUUID() }; options.body = JSON.stringify(body); }
    const r = await fetch(path, options); const result = await r.json(); if (!r.ok) throw new Error(result.errors?.[0]?.code || `HTTP ${r.status}`); if (result.data?.csrf) csrf = result.data.csrf; return result.data;
  }
  function scopeQuery() { return `customer=${encodeURIComponent(customer)}`; }
  function button(label, fn) { const b = el('button', label); b.type = 'button'; b.addEventListener('click', async () => { b.disabled = true; try { await fn(); } catch (e) { notice(e.message); } finally { b.disabled = false; } }); return b; }
  const labels = { service_state: 'Hizmet durumu', state: 'Fiziksel durum', status: 'İşlem durumu', currency: 'Para birimi', total_minor: 'Toplam (kuruş / minor unit)', net_minor: 'Net (minor unit)', tax_minor: 'Vergi (minor unit)', difference_minor: 'Fatura farkı (minor unit)', cost_total_minor: 'Kayıtlı maliyet (minor unit)', starts_at: 'Başlangıç', ends_at: 'Bitiş', expires_at: 'Bitiş tarihi', created_at: 'Kayıt zamanı', provenance: 'Kanıt türü', kind: 'İşlem', nameservers: 'Nameserver', locked: 'Kilitli', reason: 'Sonuç gerekçesi', safe_code: 'İnceleme nedeni', lease_until: 'İşçi yetkisi sonu', runner_hold_code: 'Bekleme nedeni', task_id: 'Görev kanıtı', resource_id: 'Kaynak kanıtı', generation: 'İşlem nesli', financial_exception: 'Finansal istisna', forecast_cost: '30 günlük maliyet tahmini', forecast_currency: 'Tahmin para birimi', margin: 'Marj oranı', approved: 'Ayrı yönetici onayı', published: 'Fiyat yayımlandı', auto_publish_allowed: 'Otomatik yayın izni', review_reason: 'İnceleme gerekçesi', proposed_price: 'Önerilen fiyat', reporting: 'Raporlama tutarı', fx: 'Raporlama kuru', observed_at: 'Kanıt zamanı', final: 'Nihai fatura', provider_invoice_is_paid: 'Sağlayıcı faturası ödendi', stop_ends_provider_cost: 'STOP maliyeti durdurur', pid: 'Satın alınan plan', osid: 'Satın alınan işletim sistemi', locationid: 'Satın alınan lokasyon', type: 'Sağlayıcı faturalama tipi' };
  function render(value) {
    if (value === null || value === undefined) return el('span', '—');
    if (typeof value !== 'object') return el('span', typeof value === 'boolean' ? (value ? 'Evet' : 'Hayır') : text(value));
    if (Array.isArray(value)) { const wrap = el('div'); if (!value.length) wrap.append(el('p', 'Kayıt yok.')); for (const item of value) wrap.append(render(item), el('hr')); return wrap; }
    const dl = el('dl'); for (const [key, val] of Object.entries(value)) { dl.append(el('dt', labels[key] || key)); const dd = el('dd'); dd.append(render(val)); dl.append(dd); } return dl;
  }
  function section(title, data, parent = $('detail')) { const box = el('section', undefined, 'provider-section'); box.append(el('h2', title)); if (data !== undefined) box.append(render(data)); parent.append(box); return box; }
  async function list() {
    const rows = await api(`${base}list?${scopeQuery()}&kind=${screen === 'domains' ? 'DOMAIN' : 'CLOUD'}&offset=${offset}`); $('items').replaceChildren();
    for (const r of rows) { const box = el('article', undefined, 'provider-card'); box.append(el('h2', r.domain?.name || r.name), el('p', `Hizmet: ${text(r.service_state)}`), el('p', r.vm ? `VM: ${text(r.vm.state)}` : text(r.domain?.state)), button('Ayrıntılar', () => detail(r.id))); $('items').append(box); }
    if (!rows.length) $('items').append(el('p', 'Bu kapsamda gösterilebilecek hizmet yok.'));
    const pages = el('div', undefined, 'provider-actions'); if (offset) pages.append(button('Önceki 50', async () => { offset = Math.max(0, offset - 50); await list(); })); if (rows.length === 50) pages.append(button('Sonraki 50', async () => { offset += 50; await list(); })); $('items').append(pages);
  }
  async function review(action, payload) {
    const body = { action, payload, customer }; const planned = await api(`${base}prepare`, body); const dlg = $('review'); dlg.replaceChildren(el('h2', 'İşlem onayı')); dlg.firstChild.id = 'review-title'; dlg.append(render(planned.plan), el('p', 'Bu onay yalnız gösterilen hizmet ve güncel durum içindir. Yeniden kurulum ve sonlandırma veri kaybına yol açabilir.'));
    const accept = button('Gösterilen işlemi açıkça onaylıyorum', async () => { await api(`${base}execute`, { ...body, confirmation: { id: planned.id, accepted: true } }); dlg.close(); notice('İstek kaydedildi. Sağlayıcı sonucu ayrıca doğrulanacak.'); await detail(payload.service_id); });
    if (planned.wait_seconds) { accept.disabled = true; dlg.append(el('p', `${planned.wait_seconds} saniye güvenlik beklemesi.`)); setTimeout(() => { if (dlg.open) accept.disabled = false; }, planned.wait_seconds * 1000); }
    dlg.append(accept, button('Vazgeç', () => dlg.close())); dlg.showModal();
  }
  async function containment(id, kind) {
    const dlg = $('review'); dlg.replaceChildren(el('h2', `Finansal istisna: ${kind}`)); dlg.firstChild.id = 'review-title'; const reason = el('textarea'); reason.required = true; reason.maxLength = 1000; const label = el('label', 'Operatör gerekçesi'); label.append(reason); dlg.append(label, el('p', 'Yalnız doğrulanmış mevcut kaynak. STOP sağlayıcı maliyetini kesin durdurmaz. Sonlandırma geri alınamaz.'));
    dlg.append(button('Kaynağı doğrula ve onay hazırla', async () => {
      const p = await api(`${base}containment-prepare`, { service_id: id, customer, kind, reason: reason.value }); dlg.replaceChildren(el('h2', 'Ayrı, geri alınamaz işlem onayı'), el('p', `${p.wait_seconds} saniye bekleme. ${kind}. Finans geçmişi değişmez.`));
      const accept = button('Finansal istisna işlemini onaylıyorum', async () => { await api(`${base}containment-execute`, { service_id: id, customer, confirmation: p.id, accepted: true }); dlg.close(); notice('Kalıcı containment işlemi sıraya alındı.'); await detail(id); }); accept.disabled = p.wait_seconds > 0; if (p.wait_seconds) setTimeout(() => { if (dlg.open) accept.disabled = false; }, p.wait_seconds * 1000); dlg.append(accept, button('Vazgeç', () => dlg.close()));
    }), button('Vazgeç', () => dlg.close())); dlg.showModal();
  }
  async function detail(id) {
    activeId = id; const r = await api(`${base}detail?${scopeQuery()}&id=${encodeURIComponent(id)}`); $('detail').replaceChildren();
    section(r.domain?.name || r.name, { 'Müşteri hizmeti': text(r.service_state), 'Fiziksel VM': text(r.vm?.state), 'Domain': text(r.domain?.state), 'Dönem': r.period, 'Satış': r.sale_price, 'Satış dönemi': r.sale_period_label, 'Kaynak': 'TEST / MOCK' });
    if (r.domain) section('Alan adı bilgileri', r.domain);
    if (r.vm) section('Satın alınmış sabit yapılandırma', r.purchased_configuration);
    const actions = section('Kullanılabilir işlemler'); const row = el('div', undefined, 'provider-actions'); actions.append(row);
    for (const kind of r.actions) { const labels = { START: 'Başlat', STOP: 'Durdur', RESTART: 'Yeniden başlat', REINSTALL: 'Satın alınan OS ile yeniden kur', TERMINATE: 'Kalıcı sonlandır' }; row.append(button(labels[kind], () => review('cloud-action', { service_id: id, kind, data: kind === 'REINSTALL' ? { osid: r.purchased_configuration.osid } : {} }))); }
    actions.append(el('p', `Desteklenmiyor: ${r.unsupported.join(', ')}.`));
    section('Kalıcı işlemler — teslim alınması tamamlandığı anlamına gelmez', r.operations);
    section('Yenileme durumu', r.renewals); const link = el('a', 'Hizmet yenileme ve iptal panelini aç'); link.href = `/${ctx}/services`; actions.append(link);
    if (ctx === 'admin') {
      section('Kaynak ve maliyet sınırı', { resource: r.resource, financial_source_valid: r.financial_source_valid, stop_ends_provider_cost: false });
      if (r.attempts) section('Registrar denemeleri', r.attempts);
      section('İnceleme işleri', r.inspection_jobs);
      for (const op of r.operations.filter(o => ['UNKNOWN', 'MANUAL_REVIEW'].includes(o.status))) {
        row.append(button(`${op.kind}: bağımsız inceleme iste`, () => review('inspect', { service_id: id, operation_id: op.id })));
        for (const j of r.inspection_jobs.filter(j => j.operation_id === op.id && j.status === 'SUCCEEDED')) {
          row.append(button(`${op.kind}: kanıtla mutabakat`, () => review('resolve', { service_id: id, operation_id: op.id, evidence_id: j.result_id, outcome: 'CONFIRMED_SUCCESS' })));
        }
      }
      if (r.vm && ['ACTIVE', 'STOPPED'].includes(r.vm.state)) for (const kind of ['STOP', 'TERMINATE']) row.append(button(`Personel finansal istisna ${kind}`, () => containment(id, kind)));
      if (screen === 'cloud-costs') {
        const c = await api(`${base}costs?${scopeQuery()}&id=${encodeURIComponent(id)}`); section('Maliyet işi — eksik kanıt sıfır gider değildir', c.work); section('Kaydedilmiş MOCK kullanım maliyeti ve raporlama FX', c.costs); section('Invoice mutabakatı — eşlik ödeme değildir', c.reconciliations);
        for (const p of c.proposals) { const box = section('FIN-010 fiyat önerisi — otomatik yayın kapalı', p); if (!p.approved) box.append(button('Öneriyi incele ve ayrı onayla', () => review('proposal-approve', { service_id: id, proposal_id: p.id }))); if (p.approved && !p.published) box.append(button('Commerce onayıyla mock fiyat yayınla', () => review('proposal-publish', { service_id: id, proposal_id: p.id }))); }
      }
    }
    $('detail').append(button('Durumu yeniden oku', () => detail(id)));
  }
  $('scope').addEventListener('submit', async e => { e.preventDefault(); customer = new FormData(e.target).get('customer') || customer; offset = 0; try { await list(); } catch (err) { notice(err.message); } });
  $('step-up').addEventListener('submit', async e => { e.preventDefault(); const f = new FormData(e.target); const body = { password: f.get('password') }; if (f.get('code')) body.code = f.get('code'); try { await api(`/api/v1/${ctx}/identity/step-up`, body); notice('Oturum yeniden doğrulandı.'); } catch (err) { notice(err.message); } finally { e.target.reset(); } });
  (async () => { try { const identity = await api(`/api/v1/${ctx}/identity/bootstrap`); csrf = identity.csrf; const status = await api(`${base}status`); customer = status.customer_id || ''; $('scope').elements.customer.value = customer; if (customer) await list(); else notice('Yetkili müşteri kapsamını seçin.'); } catch (err) { notice(err.message); } })();
})();
