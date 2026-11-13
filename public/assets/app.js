document.addEventListener('DOMContentLoaded', () => {
  // Cegah kirim ganda: ketukan kedua pada "Simpan" (koneksi HP lambat) tidak boleh mencatat transaksi dua kali.
  // Pengendali onsubmit bawaan (konfirmasi, prompt alasan) berjalan lebih dulu; bila membatalkan, form tidak dikunci.
  [...document.forms].filter(form => form.method === 'post').forEach(form => form.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    if (form.dataset.sent) { e.preventDefault(); return; }
    form.dataset.sent = '1';
    setTimeout(() => form.querySelectorAll('[type=submit]').forEach(b => { b.disabled = true; }), 0);
  }));
  // Tombol Kembali di browser memulihkan halaman dari cache: buka kunci agar form bisa dipakai lagi.
  addEventListener('pageshow', () => document.querySelectorAll('form[data-sent]').forEach(form => {
    delete form.dataset.sent;
    form.querySelectorAll('[type=submit]').forEach(b => { b.disabled = false; });
  }));

  const menu = document.getElementById('menuButton');
  const sidebar = document.getElementById('sidebar');
  if (menu && sidebar) {
    // Menu samping di HP: latar penutup, tombol tutup, Esc, dan tautan menu menutupnya. aria-expanded mengikuti keadaan.
    const setNav = open => {
      sidebar.classList.toggle('open', open);
      document.body.classList.toggle('nav-open', open);
      menu.setAttribute('aria-expanded', String(open));
      if (open) sidebar.querySelector('.sidebar-close')?.focus();
      else if (document.activeElement && sidebar.contains(document.activeElement)) menu.focus();
    };
    menu.addEventListener('click', () => setNav(!sidebar.classList.contains('open')));
    document.querySelectorAll('[data-nav-close]').forEach(el => el.addEventListener('click', () => setNav(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && sidebar.classList.contains('open')) setNav(false); });
    sidebar.querySelectorAll('a.nav-item').forEach(a => a.addEventListener('click', () => setNav(false)));
  }

  // Kolom nominal [data-rupiah]: tampilkan pemisah ribuan saat mengetik (1500000 -> 1.500.000).
  const bindRupiah = input => {
    const format = () => {
      const digits = input.value.replace(/\D/g, '').slice(0, 12);
      input.value = digits ? Number(digits).toLocaleString('id-ID') : '';
    };
    input.addEventListener('input', format);
    format();
  };
  document.querySelectorAll('input[data-rupiah]').forEach(bindRupiah);

  // Jurnal manual: tambah/hapus baris, satu sisi (debit atau kredit) per baris, dan penanda seimbang langsung.
  document.querySelectorAll('[data-journal-form]').forEach(form => {
    const body = form.querySelector('[data-journal-lines]');
    const template = form.querySelector('[data-journal-template]');
    const note = form.querySelector('[data-journal-balance]');
    let next = body.querySelectorAll('[data-journal-row]').length;
    const amount = el => Number(String(el.value).replace(/\D/g, '')) || 0;
    const rp = n => 'Rp ' + n.toLocaleString('id-ID');
    const totals = () => {
      let debit = 0, credit = 0;
      body.querySelectorAll('[data-journal-row]').forEach(row => {
        debit += amount(row.querySelector('[data-side=debit]'));
        credit += amount(row.querySelector('[data-side=credit]'));
      });
      form.querySelector('[data-journal-debit]').textContent = rp(debit);
      form.querySelector('[data-journal-credit]').textContent = rp(credit);
      note.textContent = debit === 0 && credit === 0 ? 'ⓘ Isi debit atau kredit pada tiap baris (salah satu saja).'
        : debit === credit ? `✓ Seimbang: ${rp(debit)}` : `⚠ Belum seimbang, selisih ${rp(Math.abs(debit - credit))}.`;
    };
    form.addEventListener('input', e => {
      const side = e.target.dataset?.side;
      if (side && amount(e.target)) e.target.closest('[data-journal-row]').querySelector(`[data-side=${side === 'debit' ? 'credit' : 'debit'}]`).value = '';
      totals();
    });
    form.addEventListener('click', e => {
      if (e.target.closest('[data-journal-add]')) {
        body.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__i__', next++));
        body.lastElementChild.querySelectorAll('input[data-rupiah]').forEach(bindRupiah);
      }
      const remove = e.target.closest('[data-journal-remove]');
      if (remove) {
        const row = remove.closest('[data-journal-row]');
        if (body.querySelectorAll('[data-journal-row]').length > 2) row.remove();
        else row.querySelectorAll('input,select').forEach(el => { el.value = ''; });
        totals();
      }
    });
    totals();
  });

  // Perkiraan cicilan anuitas di form pengajuan pinjaman (rumus sama dengan server: Loan::annuity).
  document.querySelectorAll('[data-loan-calc]').forEach(form => {
    const f = form.elements;
    const note = form.querySelector('[data-loan-estimate]');
    const sync = () => {
      const p = Number(String(f.principal.value).replace(/\D/g, ''));
      const n = Number(f.term_months.value);
      const rate = Number(f.product.selectedOptions[0]?.dataset.rate);
      if (!p || !n || !rate) return;
      const r = rate / 1200;
      const pmt = Math.round(p * r / (1 - Math.pow(1 + r, -n)));
      note.textContent = `ⓘ Perkiraan cicilan Rp ${pmt.toLocaleString('id-ID')} per bulan selama ${n} bulan (total bunga ± Rp ${Math.max(0, pmt * n - p).toLocaleString('id-ID')}).`;
    };
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
  });

  // Tab di Pusat Approval: tombol [data-approval-tab="status"] menyaring baris [data-approval-row].
  document.querySelectorAll('[data-approval-tab]').forEach(tab => tab.addEventListener('click', () => {
    document.querySelectorAll('[data-approval-tab]').forEach(t => t.classList.toggle('active', t === tab));
    document.querySelectorAll('[data-approval-row]').forEach(row => {
      row.hidden = tab.dataset.approvalTab !== '' && row.dataset.approvalRow !== tab.dataset.approvalTab;
    });
  }));

  // Panel pemberitahuan: ketuk lonceng untuk membuka/menutup; tutup bila mengetuk di luar atau menekan Esc.
  document.querySelectorAll("[data-notify]").forEach(root => {
    const toggle = root.querySelector("[data-notify-toggle]");
    const panel = root.querySelector("[data-notify-panel]");
    const set = open => { panel.hidden = !open; toggle.setAttribute("aria-expanded", String(open)); };
    toggle.addEventListener("click", () => set(panel.hidden));
    document.addEventListener("click", e => { if (!root.contains(e.target)) set(false); });
    document.addEventListener("keydown", e => { if (e.key === "Escape") set(false); });
  });

  // Form jenis transaksi baru: pilihan akun mengikuti kategori (penerimaan = pendapatan, pengeluaran = beban).
  document.querySelectorAll('[data-type-category]').forEach(category => {
    const account = category.form.elements.account_code;
    if (!account) return; // mode tanpa akuntan: akun tidak dipilih
    const all = [...account.options];
    const sync = () => {
      const chosen = account.value;
      const keep = all.filter(o => !o.value || o.dataset.category === category.value);
      account.replaceChildren(...keep);
      account.value = keep.some(o => o.value === chosen) ? chosen : '';
    };
    category.addEventListener('change', sync);
    sync();
  });

  // Kotak pilih dengan daftar turun sendiri (ketik untuk menyaring, ketuk untuk memilih), ramah layar sentuh:
  //   <input list="dl" data-combobox="nama"> + <datalist id="dl"><option value="teks" data-id="1"> + <input type="hidden" name="nama">
  // Datalist hanya dipakai sebagai sumber data; dropdown bawaan browser dimatikan karena di Android hanya
  // tampil di bilah keyboard. Yang dikirim ke server adalah id di input tersembunyi.
  document.querySelectorAll('input[data-combobox]').forEach(input => {
    const hidden = input.form.elements[input.dataset.combobox];
    const options = [...document.getElementById(input.getAttribute('list')).options].map(o => ({ label: o.value, id: o.dataset.id }));
    input.removeAttribute('list');
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-expanded', 'false');

    const wrap = document.createElement('div');
    wrap.className = 'combo';
    input.parentNode.insertBefore(wrap, input);
    const list = document.createElement('ul');
    list.className = 'combo-list';
    list.setAttribute('role', 'listbox');
    list.hidden = true;
    wrap.append(input, list);

    let shown = [];
    let active = -1;
    const exact = () => options.find(o => o.label === input.value);
    const validate = () => input.setCustomValidity(input.value && !exact() ? 'Pilih salah satu dari daftar yang muncul.' : '');
    const close = () => { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
    const highlight = i => {
      active = i;
      [...list.children].forEach((li, n) => li.classList.toggle('active', n === i));
      list.children[i]?.scrollIntoView({ block: 'nearest' });
    };
    const render = () => {
      const q = exact() ? '' : input.value.toLowerCase().trim(); // sudah memilih: tampilkan semua agar bisa ganti
      shown = options.filter(o => !q || o.label.toLowerCase().includes(q));
      const items = shown.map((o, i) => {
        const li = document.createElement('li');
        li.textContent = o.label;
        li.setAttribute('role', 'option');
        li.dataset.index = i;
        return li;
      });
      if (!items.length) {
        const empty = document.createElement('li');
        empty.className = 'combo-empty';
        empty.textContent = 'Tidak ada yang cocok';
        items.push(empty);
      }
      list.replaceChildren(...items);
      list.hidden = false;
      input.setAttribute('aria-expanded', 'true');
      highlight(-1);
    };
    const choose = o => {
      input.value = o.label;
      hidden.value = o.id;
      validate();
      close();
      hidden.dispatchEvent(new Event('change', { bubbles: true }));
    };
    // Meninggalkan kolom setelah mengetik: bila tepat satu pilihan cocok, langsung dipilih.
    const settle = () => {
      if (!exact() && input.value.trim() && shown.length === 1) return choose(shown[0]);
      hidden.value = exact()?.id ?? '';
      validate();
      close();
    };

    const current = options.find(o => o.id === hidden.value);
    if (current) input.value = current.label;

    input.addEventListener('focus', () => {
      input.select();
      render();
      setTimeout(() => wrap.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }), 250); // agar daftar tidak tertutup keyboard
    });
    input.addEventListener('input', () => { hidden.value = exact()?.id ?? ''; validate(); render(); });
    input.addEventListener('blur', () => setTimeout(settle, 120));
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) render(); highlight(Math.min(active + 1, shown.length - 1)); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(Math.max(active - 1, 0)); }
      else if (e.key === 'Escape') { close(); }
      else if (e.key === 'Enter') {
        if (!list.hidden && shown[active]) { e.preventDefault(); choose(shown[active]); }
        else if (!list.hidden && shown.length === 1) { e.preventDefault(); choose(shown[0]); }
        else if (!exact()) { e.preventDefault(); }
      }
    });
    // Memilih pada "click" (jari diangkat tanpa bergeser), BUKAN saat jari menyentuh layar: di layar sentuh, menggulir daftar
    // dimulai dengan sentuhan, jadi memilih saat sentuh membuat daftar tidak bisa digulir.
    // mousedown dicegah supaya kolom tidak kehilangan fokus (dan daftar tidak tertutup) sebelum click terjadi.
    list.addEventListener('mousedown', e => e.preventDefault());
    list.addEventListener('click', e => {
      const li = e.target.closest('li[data-index]');
      if (!li) return;
      choose(shown[Number(li.dataset.index)]);
      if (matchMedia('(pointer: coarse)').matches) input.blur(); // tutup keyboard di HP setelah memilih
    });
    validate();
  });

  // Form Kasir: pilihan pinjaman mengikuti anggota, pilihan akun mengikuti kategori.
  // Opsi disusun ulang (bukan di-hidden) supaya juga jalan di dropdown bawaan Android.
  document.querySelectorAll('[data-kasir-form]').forEach(form => {
    const f = form.elements;
    const loanField = form.querySelector('[data-loan-field]');
    const loanEmpty = form.querySelector('[data-loan-empty]');
    const typeField = form.querySelector('[data-type-field]');
    const all = new Map();
    const narrow = (select, keep) => {
      if (!all.has(select)) all.set(select, [...select.options]);
      const chosen = select.value;
      const placeholder = all.get(select).filter(o => !o.value);
      const real = all.get(select).filter(o => o.value && keep(o));
      select.replaceChildren(...placeholder, ...real);
      select.value = real.some(o => o.value === chosen) ? chosen : '';
      if (!select.value && !placeholder.length) select.selectedIndex = 0; // dropdown tanpa pilihan kosong (kategori)
      return real;
    };
    const sync = () => {
      // Angsuran selalu milik seorang anggota: tidak ditawarkan untuk "Transaksi Umum".
      narrow(f.category, o => o.value !== 'Angsuran' || f.member_id.value !== '');
      const category = f.category.value;
      const loans = narrow(f.loan_id, o => o.dataset.member === f.member_id.value);
      loanField.hidden = category !== 'Angsuran' || loans.length === 0;
      loanEmpty.hidden = !(category === 'Angsuran' && loans.length === 0);
      if (loans.length === 1 && !f.loan_id.value) f.loan_id.value = loans[0].value;
      // Jenis transaksi mengikuti kategori; Angsuran dan Saldo Awal tidak memakai jenis.
      const types = narrow(f.jenis_id, o => o.dataset.category === category);
      if (types.length === 1) f.jenis_id.value = types[0].value;
      typeField.hidden = types.length === 0;
    };
    form.addEventListener('change', sync);
    sync();
  });

  // Tabel: kotak cari [data-table-search="#id"] + dropdown [data-filter-table="#id" data-filter-col="n"]
  // (n = nomor kolom mulai 0; value kosong = semua). Semua filter berlaku bersamaan.
  document.querySelectorAll('[data-table-search],[data-filter-table]').forEach(control => {
    const sel = control.dataset.tableSearch || control.dataset.filterTable;
    const table = document.querySelector(sel);
    if (!table) return;
    control.addEventListener(control.tagName === 'SELECT' ? 'change' : 'input', () => {
      const q = (document.querySelector(`[data-table-search="${sel}"]`)?.value || '').toLowerCase().trim();
      const filters = [...document.querySelectorAll(`[data-filter-table="${sel}"]`)];
      table.querySelectorAll('tbody tr').forEach(row => {
        const show = row.innerText.toLowerCase().includes(q)
          && filters.every(f => !f.value || row.cells[f.dataset.filterCol].innerText.includes(f.value));
        row.style.display = show ? '' : 'none';
      });
    });
  });
});
