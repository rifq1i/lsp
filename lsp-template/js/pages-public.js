import { ICONS, showToast } from './layout.js';
import { formatRupiah, getSchemeById, statusBadgeClass } from './store.js';

export function renderHomePage(db) {
  return `
    <section class="hero">
      <div class="container hero-grid">
        <div>
          <div class="hero-badge">${ICONS.shield} Resmi Terlisensi BNSP · Fotografi Studio</div>
          <h1>Sertifikasi <span class="accent">Fotografi Studio</span> Standar Nasional.</h1>
          <p class="hero-lead">Validasi kompetensi pencahayaan studio, pemotretan produk komersial, dan post-production melalui uji kompetensi transparan dengan jaminan SLA 5 hari kerja.</p>
          <div class="hero-actions">
            <a href="#/register" class="btn btn-orange">Daftar Sertifikasi</a>
            <a href="#/skema" class="btn btn-outline-light">Lihat Skema</a>
            <a href="#/alur" class="hero-play-btn" title="Alur Sertifikasi">${ICONS.arrow}</a>
          </div>
        </div>
        <div class="hero-visual">
          <div class="hero-image-frame">
            <img src="https://images.unsplash.com/photo-1542038784456-1ea8e935640e?auto=format&fit=crop&w=1000&q=80" alt="Sesi Uji Fotografi Studio">
          </div>
          <div class="hero-floating-card">
            <div class="hero-floating-icon">${ICONS.award}</div>
            <div>
              <strong style="display:block;font-size:17px;">1.250+ Asesi</strong>
              <span style="font-size:12px;color:var(--text-muted);">Tersertifikasi Kompeten BNSP</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <div class="trust-strip">
      <div class="container">
        <div class="trust-label">TERAKREDITASI & BERSINERGI DENGAN EKOSISTEM INDUSTRI</div>
        <div class="trust-logos">
          <span class="trust-item">${ICONS.shield} BNSP Republik Indonesia</span>
          <span class="trust-item">${ICONS.camera} SKKNI Fotografi Nasional</span>
          <span class="trust-item">${ICONS.award} Asosiasi Studio Komersial</span>
          <span class="trust-item">${ICONS.users} 18+ TUK Terverifikasi</span>
        </div>
      </div>
    </div>

    <section class="section" style="padding-top:24px;">
      <div class="container">
        <div class="grid-3">
          <div class="highlight-card">
            <div>
              <div class="card-icon">${ICONS.file}</div>
              <h3>Pendaftaran & APL Digital</h3>
              <p>Pengajuan FR-APL-01 dan asesmen mandiri APL-02 sepenuhnya daring dengan verifikasi admin maksimal 1x24 jam.</p>
            </div>
            <a href="#/alur" class="text-link">Pelajari Alur ${ICONS.arrow}</a>
          </div>

          <div class="highlight-card featured">
            <div>
              <div class="card-icon">${ICONS.camera}</div>
              <h3>Uji Praktik Studio Nyata</h3>
              <p>Asesmen langsung menggunakan fasilitas cyclorama, strobe profesional, dan kalibrasi warna bersama Asesor berlisensi MET BNSP.</p>
            </div>
            <a href="#/tuk" class="text-link">Lihat Fasilitas TUK ${ICONS.arrow}</a>
          </div>

          <div class="highlight-card">
            <div>
              <div class="card-icon">${ICONS.award}</div>
              <h3>E-Sertifikat Terverifikasi</h3>
              <p>Terbit resmi dengan nomor registrasi nasional yang dapat divalidasi instan kapan saja oleh klien atau instansi.</p>
            </div>
            <a href="#/cek-sertifikat" class="text-link">Verifikasi Sertifikat ${ICONS.arrow}</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container split-feature">
        <div class="collage-wrap">
          <div class="collage-badge">
            <strong>5 Hari</strong>
            <span>Maks. SLA Terbit</span>
          </div>
          <div class="collage-main">
            <img src="https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=80" alt="Asesmen Studio">
          </div>
        </div>
        <div>
          <span class="kicker">MENGAPA NUSAXNINE LSP</span>
          <h2 style="font-size:clamp(28px,3.5vw,38px);font-weight:800;line-height:1.15;letter-spacing:-0.03em;margin-bottom:14px;">
            Alur Sertifikasi Terukur. Hasil Diakui Industri.
          </h2>
          <p style="color:var(--text-muted);font-size:15px;">
            Kami memangkas birokrasi berbelit tanpa mengurangi ketatnya standar SKKNI Fotografi. Setiap tahapan dipantau secara real-time melalui dashboard asesi.
          </p>
          <ul class="check-list">
            <li><span class="check-dot orange">${ICONS.check}</span> SLA Verifikasi & Penjadwalan 1x24 Jam per Tahap</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Penjadwalan Otomatis H+2 Setelah Pembayaran Lunas</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Materi Uji Kompetensi (MUK) Standar FR.IA.02 – FR.IA.07</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Unduhan Otomatis PDF FR-APL-01, MUK, dan E-Sertifikat</li>
          </ul>
          <a href="#/tentang" class="btn btn-orange">Tentang Legalitas LSP</a>
        </div>
      </div>
    </section>

    <div class="section-rounded-wrap">
      <section class="section-rounded-dark">
        <div class="container">
          <div class="section-head center">
            <span class="kicker kicker-orange">SKEMA POPULER BNSP</span>
            <h2>Semua Kebutuhan Sertifikasi Studio Dalam Satu Atap</h2>
            <p>Pilih skema okupasi atau klaster sesuai spesialisasi fotografi studio dan target jenjang karier Anda.</p>
          </div>
          <div class="grid-4">
            ${db.schemes.map(s => `
              <div class="scheme-card-white">
                <div>
                  <span class="scheme-meta">${s.level}</span>
                  <h3>${s.name}</h3>
                  <p>${s.summary}</p>
                </div>
                <div>
                  <div style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:12px;">${s.units.length} Unit Kompetensi · ${s.duration}</div>
                  <a href="#/skema/${s.id}" class="text-link">Detail Skema ${ICONS.arrow}</a>
                </div>
              </div>
            `).join('')}
          </div>
          <div style="text-align:center;margin-top:36px;">
            <a href="#/skema" class="btn btn-orange">Lihat Seluruh Katalog Skema</a>
          </div>
        </div>
      </section>
    </div>

    <section class="section">
      <div class="container">
        <div class="section-head center">
          <span class="kicker">DOKUMENTASI & TUK</span>
          <h2>Aktivitas Uji Kompetensi Studio</h2>
          <p>Potret suasana asesmen praktik, fasilitas TUK terverifikasi, dan pembekalan asesi terbaru.</p>
        </div>
        <div class="filter-pills" id="home-gallery-filters">
          ${['Semua', 'Uji Studio', 'TUK Terverifikasi', 'Pengumuman', 'Galeri'].map((cat, idx) => `
            <button class="pill-btn ${idx === 0 ? 'active' : ''}" data-filter="${cat}">${cat}</button>
          `).join('')}
        </div>
        <div class="grid-3" id="home-gallery-grid">
          ${db.articles.map(a => `
            <article class="gallery-card" data-category="${a.category}">
              <div class="gallery-thumb"><img src="${a.img}" alt="${a.title}"></div>
              <div class="gallery-body">
                <span class="badge badge-sky" style="margin-bottom:8px;">${a.category}</span>
                <h4>${a.title}</h4>
                <p>${a.date} · ${a.excerpt}</p>
              </div>
            </article>
          `).join('')}
        </div>
      </div>
    </section>

    <section class="section section-dark">
      <div class="container">
        <div class="section-head center">
          <span class="kicker kicker-orange">BIAYA & PAKET ASESMEN</span>
          <h2>Transparansi Biaya Uji Kompetensi</h2>
          <p>Mencakup akses portal FR-APL, lembar MUK digital, fasilitas TUK studio, honorarium asesor, dan penerbitan E-Sertifikat.</p>
        </div>
        <div class="grid-3">
          ${db.schemes.slice(0, 3).map((s, idx) => `
            <div class="plan-card ${idx === 1 ? 'featured' : ''}">
              <div>
                <span class="badge ${idx === 1 ? 'badge-navy' : 'badge-sky'}">${s.level}</span>
                <h3 style="font-size:20px;font-weight:800;margin-top:14px;">${s.name}</h3>
                <div class="plan-price">${formatRupiah(s.fee)} <span>/ Asesi</span></div>
                <ul class="check-list" style="margin:14px 0 24px;">
                  ${s.units.slice(0, 3).map(u => `
                    <li style="font-size:13.5px;"><span class="check-dot">${ICONS.check}</span> ${u.title}</li>
                  `).join('')}
                </ul>
              </div>
              <a href="#/skema/${s.id}" class="btn btn-navy btn-block">Pilih Skema Ini</a>
            </div>
          `).join('')}
        </div>
      </div>
    </section>

    <section class="section section-ice">
      <div class="container">
        <div class="section-head center">
          <span class="kicker">TESTIMONI ASESI</span>
          <h2>Kata Para Fotografer Tersertifikasi</h2>
        </div>
        <div class="testimonial-box">
          <div class="quote-mark">“</div>
          <p style="font-size:17px;font-weight:600;color:var(--navy);margin-bottom:22px;">
            Proses pengajuan FR-APL-01 sampai jadwal uji di TUK benar-benar rapi dan cepat. E-Sertifikat BNSP Fotografi Produk sangat membantu studio kami memenangkan tender katalog brand nasional.
          </p>
          <strong>Rama Prakoso</strong>
          <div style="font-size:13px;color:var(--text-muted);">Lead Commercial Photographer · Skema SS-FOTO-PRD-002-2026</div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container faq-split">
        <div>
          <span class="kicker">PERTANYAAN UMUM</span>
          <h2 style="font-size:32px;font-weight:800;margin-bottom:20px;">Frequently Asked Questions</h2>
          ${[
            { q: 'Berapa lama proses sertifikasi sampai E-Sertifikat terbit?', a: 'Maksimal 5 hari kerja sejak berkas APL-01 dan APL-02 dinyatakan lengkap, dengan SLA verifikasi 1x24 jam di setiap tahapan.' },
            { q: 'Apakah saya harus membawa kamera dan lampu studio sendiri?', a: 'TUK Terverifikasi telah menyediakan perangkat lighting strobe, modifier, dan latar studio. Peserta disarankan membawa kamera DSLR/Mirrorless pribadi yang biasa digunakan.' },
            { q: 'Bagaimana jika hasil asesmen dinyatakan Belum Kompeten (BK)?', a: 'Peserta berhak mendapatkan umpan balik detail dari Asesor, mengajukan banding resmi melalui menu Kontak, atau mengikuti asesmen ulang pada unit yang belum kompeten.' },
            { q: 'Apakah sertifikat berlaku di seluruh Indonesia?', a: 'Ya, sertifikat diterbitkan dengan lisensi resmi BNSP berlogo Garuda Emas yang diakui secara nasional dan ASEAN.' }
          ].map((f, i) => `
            <div class="faq-item ${i === 0 ? 'open' : ''}">
              <button class="faq-q" data-faq-toggle>${f.q} <span>+</span></button>
              <div class="faq-a">${f.a}</div>
            </div>
          `).join('')}
        </div>
        <div class="dark-cta-card">
          <span class="badge badge-orange" style="margin-bottom:14px;">KONSULTASI SKEMA</span>
          <h3>Siap Naik Level Sebagai Fotografer Tersertifikasi?</h3>
          <p style="margin-bottom:18px;">Masih bingung menentukan skema okupasi atau klaster yang cocok dengan portofolio studio Anda?</p>
          <ul class="check-list" style="color:var(--white);margin-bottom:24px;">
            <li><span class="check-dot orange">${ICONS.check}</span> Cek kecocokan portofolio gratis</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Panduan pengisian FR-APL-01 & 02</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Jadwal fleksibel di 3 lokasi TUK</li>
          </ul>
          <a href="#/register" class="btn btn-orange btn-block">Daftar Akun Pemohon</a>
        </div>
      </div>
    </section>

    <section class="section" style="padding-top:0;">
      <div class="container">
        <div class="verify-banner">
          <div class="verify-banner-content">
            <span class="badge badge-navy" style="margin-bottom:12px;">WIDGET VERIFIKASI INSTAN</span>
            <h2>Cek Status Permohonan & Keaslian Sertifikat</h2>
            <p style="font-size:14.5px;opacity:0.9;">Masukkan ID Izin (contoh: <strong>IZN-2026-0101</strong>) atau Nomor Sertifikat (<strong>SKK-FOTO-2026-00891</strong>).</p>
            <form class="verify-banner-form" id="home-verify-form">
              <input type="text" id="home-verify-input" placeholder="Masukkan ID Izin atau No. Sertifikat..." required>
              <button type="submit" class="btn btn-navy">Cek Sekarang</button>
            </form>
            <div id="home-verify-result" style="margin-top:16px;"></div>
          </div>
          <div class="verify-banner-img">
            <img src="https://images.unsplash.com/photo-1520390138845-fd2d229dd553?auto=format&fit=crop&w=900&q=80" alt="Verifikasi Sertifikat Studio">
          </div>
        </div>

        <div class="stats-bar">
          <div class="stat-item"><div class="stat-icon">${ICONS.users}</div><div><strong>1.250+</strong><span>Asesi Kompeten</span></div></div>
          <div class="stat-item"><div class="stat-icon">${ICONS.camera}</div><div><strong>${db.schemes.length} Skema</strong><span>Fotografi Studio</span></div></div>
          <div class="stat-item"><div class="stat-icon">${ICONS.shield}</div><div><strong>${db.tuks.length} TUK</strong><span>Terverifikasi BNSP</span></div></div>
          <div class="stat-item"><div class="stat-icon">${ICONS.award}</div><div><strong>${db.assessors.length} Asesor</strong><span>Berlisensi MET</span></div></div>
        </div>
      </div>
    </section>
  `;
}

export function renderAboutPage() {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">PROFIL LEMBAGA</span>
        <h1>Tentang NusaXNine LSP Fotografi Studio</h1>
        <p>Lembaga Sertifikasi Profesi Pihak Ketiga independen yang berfokus menjaga standar kualitas tenaga kerja fotografi studio di Indonesia.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid-2" style="margin-bottom:36px;">
          <div class="card-ice">
            <span class="badge badge-navy" style="margin-bottom:12px;">VISI</span>
            <h3 style="font-size:22px;font-weight:800;margin-bottom:10px;">Menjadi Rujukan Nasional Sertifikasi Fotografi Studio</h3>
            <p style="color:var(--text-muted);">Mencetak ekosistem fotografer studio, lighting director, dan digital imaging artist Indonesia yang diakui kompetensinya di tingkat nasional maupun internasional.</p>
          </div>
          <div class="card">
            <span class="badge badge-orange" style="margin-bottom:12px;">MISI</span>
            <ul class="check-list" style="margin:8px 0 0;">
              <li><span class="check-dot orange">${ICONS.check}</span> Menyelenggarakan uji kompetensi objektif, imparsial, dan transparan sesuai pedoman BNSP.</li>
              <li><span class="check-dot orange">${ICONS.check}</span> Memelihara mutu TUK studio serta pemutakhiran skema mengikuti teknologi kamera & pencahayaan.</li>
              <li><span class="check-dot orange">${ICONS.check}</span> Menjamin layanan digital cepat dengan SLA 1x24 jam di setiap tahapan.</li>
            </ul>
          </div>
        </div>

        <div class="grid-3" style="margin-bottom:44px;">
          <div class="card">
            <span class="kicker">LEGALITAS BNSP</span>
            <h4 style="font-size:18px;font-weight:800;margin-bottom:8px;">BNSP-LSP-2409-ID</h4>
            <p style="font-size:13.5px;color:var(--text-muted);">SK Ketua BNSP Nomor KEP.2409/BNSP/III/2025 tentang Pemberian Lisensi LSP Pihak Ketiga Bidang Fotografi Studio.</p>
          </div>
          <div class="card">
            <span class="kicker">AKTA PENDIRIAN</span>
            <h4 style="font-size:18px;font-weight:800;margin-bottom:8px;">AHU-0019284.AH.01.07</h4>
            <p style="font-size:13.5px;color:var(--text-muted);">Terdaftar resmi di Kementerian Hukum dan HAM RI sebagai badan hukum pelaksana sertifikasi kompetensi independen.</p>
          </div>
          <div class="card">
            <span class="kicker">SEKRETARIAT RESMI</span>
            <h4 style="font-size:18px;font-weight:800;margin-bottom:8px;">Jakarta Pusat</h4>
            <p style="font-size:13.5px;color:var(--text-muted);">Gedung NusaXNine Creative Hub Lt. 3, Tanah Abang, Jakarta Pusat. Operasional Senin–Jumat 08.00–17.00 WIB.</p>
          </div>
        </div>

        <div class="section-head">
          <span class="kicker">TATA KELOLA</span>
          <h2>Struktur Organisasi LSP</h2>
        </div>
        <div class="grid-4">
          ${[
            { role: 'Ketua Pengarah', name: 'Ir. Bagus Prakasa', desc: 'Pengawasan imparsialitas & kebijakan strategis.' },
            { role: 'Direktur LSP', name: 'Hendra Gunawan', desc: 'Penanggung jawab operasional & lisensi BNSP.' },
            { role: 'Manajer Sertifikasi', name: 'Nadia Kusuma, M.Ds.', desc: 'Pengelolaan skema, MUK, dan penugasan asesor.' },
            { role: 'Manajer Mutu & TUK', name: 'Raka Mahendra, S.Sn.', desc: 'Audit internal, surveilan TUK, dan kepatuhan SLA.' }
          ].map(o => `
            <div class="card">
              <span class="badge badge-sky" style="margin-bottom:10px;">${o.role}</span>
              <h4 style="font-size:17px;font-weight:800;margin-bottom:6px;">${o.name}</h4>
              <p style="font-size:13px;color:var(--text-muted);">${o.desc}</p>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderSchemesPage(db) {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">KATALOG SKEMA BNSP</span>
        <h1>Skema Sertifikasi Fotografi Studio</h1>
        <p>Seluruh skema mengacu pada SKKNI Bidang Fotografi terbaru untuk jenjang pemula hingga direktur pencahayaan studio.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid-2">
          ${db.schemes.map(s => `
            <div class="card" style="display:flex;flex-direction:column;justify-content:space-between;">
              <div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                  <span class="badge badge-navy">${s.code}</span>
                  <span class="badge badge-orange">${s.level}</span>
                </div>
                <h3 style="font-size:22px;font-weight:800;margin-bottom:10px;">${s.name}</h3>
                <p style="color:var(--text-muted);font-size:14.5px;margin-bottom:18px;">${s.summary}</p>
                <div style="display:flex;gap:18px;font-size:13px;font-weight:700;color:var(--navy);margin-bottom:22px;flex-wrap:wrap;">
                  <span>${s.units.length} Unit Kompetensi</span>
                  <span>·</span>
                  <span>${s.duration}</span>
                  <span>·</span>
                  <span style="color:var(--blue);">${formatRupiah(s.fee)}</span>
                </div>
              </div>
              <div style="display:flex;gap:12px;">
                <a href="#/skema/${s.id}" class="btn btn-navy">Lihat Unit & Syarat</a>
                <a href="#/register" class="btn btn-orange">Ajukan Skema</a>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderSchemeDetailPage(db, id) {
  const s = getSchemeById(db, id);
  return `
    <section class="page-hero">
      <div class="container">
        <a href="#/skema" style="color:var(--orange);font-size:13.5px;font-weight:700;display:inline-block;margin-bottom:14px;">← Kembali ke Katalog Skema</a>
        <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;">
          <span class="badge badge-orange">${s.code}</span>
          <span class="badge badge-blue">${s.level}</span>
        </div>
        <h1>${s.name}</h1>
        <p>${s.summary}</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="faq-split">
          <div>
            <h2 style="font-size:24px;font-weight:800;margin-bottom:16px;">Daftar Unit Kompetensi</h2>
            <div class="table-wrap" style="margin-bottom:32px;">
              <table>
                <thead><tr><th>No</th><th>Kode Unit</th><th>Judul Unit Kompetensi</th></tr></thead>
                <tbody>
                  ${s.units.map((u, i) => `
                    <tr>
                      <td><strong>${i + 1}</strong></td>
                      <td><span class="badge badge-sky">${u.code}</span></td>
                      <td><strong>${u.title}</strong></td>
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            </div>

            <h2 style="font-size:24px;font-weight:800;margin-bottom:14px;">Persyaratan Dasar Pemohon</h2>
            <div class="card-ice">
              <ul class="check-list" style="margin:0;">
                ${s.requirements.map(r => `<li><span class="check-dot orange">${ICONS.check}</span> ${r}</li>`).join('')}
              </ul>
            </div>
          </div>

          <div class="dark-cta-card">
            <span class="badge badge-orange" style="margin-bottom:12px;">RINGKASAN BIAYA</span>
            <h3>${formatRupiah(s.fee)}</h3>
            <p style="margin-bottom:20px;">Durasi Asesmen: ${s.duration} · Metode: Observasi Praktik Studio, Tes Tertulis (FR.IA.05/06) & Wawancara Lisan (FR.IA.07).</p>
            <a href="#/dashboard/pengajuan" class="btn btn-orange btn-block" style="margin-bottom:10px;">Ajukan FR-APL-01 Sekarang</a>
            <a href="#/jadwal" class="btn btn-outline-light btn-block">Cek Jadwal Tersedia</a>
          </div>
        </div>
      </div>
    </section>
  `;
}

export function renderFlowPage() {
  const steps = [
    { no: '01', code: 'Cek -> Pemohon', title: 'Registrasi & Pengajuan FR-APL-01', sla: 'Hari ke-1', desc: 'Pemohon membuat akun, memilih skema fotografi studio, mengisi data pribadi & pekerjaan, serta mengunggah bukti persyaratan dasar.' },
    { no: '02', code: 'Pemohon -> Apl_01', title: 'Asesmen Mandiri (APL-02) & Verifikasi', sla: 'SLA 1x24 Jam', desc: 'Pemohon mengisi penilaian mandiri APL-02 dan menyetujui kebenaran data. Admin LSP memverifikasi kelengkapan berkas.' },
    { no: '03', code: 'Peserta -> Tagih -> Lunas', title: 'Penerbitan Tagihan & Pembayaran', sla: 'SLA 1x24 Jam', desc: 'Pemohon berstatus Peserta menerima invoice resmi, mengunggah bukti transfer, dan Admin memvalidasi status menjadi Lunas.' },
    { no: '04', code: 'Lunas -> Assesor -> Pra_Assesment', title: 'Penjadwalan Uji (H+2) & Penugasan Asesor', sla: 'H+2 Setelah Lunas', desc: 'Admin menetapkan jadwal TUK dan Asesor berlisensi MET. Peserta menyetujui formulir kerahasiaan asesmen.' },
    { no: '05', code: 'Assesment -> Assesor_Yes/No', title: 'Pelaksanaan MUK & Penerbitan E-Sertifikat', sla: 'Maks. Hari ke-5', desc: 'Uji praktik studio (FR.IA.02/03) & MUK (FR.IA.05, 06, 07). Jika dinyatakan Kompeten, Nomor Sertifikat & E-Sertifikat resmi terbit.' }
  ];

  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">STANDAR OPERASIONAL PROSEDUR</span>
        <h1>Alur & SLA Sertifikasi Kompetensi</h1>
        <p>Komitmen layanan tepat waktu: maksimal 1x24 jam pada setiap antrean verifikasi dan total proses maksimal 5 hari kerja.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="sla-alert" style="margin-bottom:36px;">
          <div>
            <strong>Aturan Batas Waktu (Service Level Agreement)</strong>
            <div style="font-size:13.5px;color:var(--text-muted);">Setiap status memiliki penghitung mundur 1x24 jam. Jadwal uji ditetapkan otomatis H+2 sejak status Lunas.</div>
          </div>
          <span class="badge badge-orange">SLA 1x24 Jam · Batas 5 Hari Kerja</span>
        </div>

        <div style="display:grid;gap:18px;">
          ${steps.map(st => `
            <div class="card" style="display:grid;grid-template-columns:80px 1fr auto;gap:20px;align-items:center;">
              <div style="width:64px;height:64px;border-radius:18px;background:var(--navy);color:var(--orange);display:grid;place-items:center;font-size:22px;font-weight:800;">${st.no}</div>
              <div>
                <span class="badge badge-sky" style="margin-bottom:6px;">${st.code}</span>
                <h3 style="font-size:19px;font-weight:800;margin-bottom:4px;">${st.title}</h3>
                <p style="font-size:14px;color:var(--text-muted);">${st.desc}</p>
              </div>
              <span class="badge badge-orange">${st.sla}</span>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderTukPage(db) {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">TEMPAT UJI KOMPETENSI</span>
        <h1>Daftar TUK Terverifikasi</h1>
        <p>Seluruh studio uji telah melalui surveilan kelayakan peralatan pencahayaan, standar K3 listrik, dan ruang ujian teori.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid-3">
          ${db.tuks.map(t => `
            <div class="card">
              <div style="display:flex;justify-content:space-between;margin-bottom:14px;">
                <span class="badge badge-navy">${t.code}</span>
                <span class="badge badge-orange">${t.type}</span>
              </div>
              <h3 style="font-size:19px;font-weight:800;margin-bottom:8px;">${t.name}</h3>
              <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:14px;">${t.facilities}</p>
              <div style="font-size:13px;font-weight:700;">Kota: ${t.city} · Kapasitas: ${t.capacity} Asesi</div>
              <div style="margin-top:12px;"><span class="badge badge-sky">${t.status}</span></div>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderAssessorsPage(db) {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">ASESOR KOMPETENSI</span>
        <h1>Daftar Asesor Aktif Berlisensi BNSP</h1>
        <p>Praktisi senior fotografi studio yang memiliki sertifikat Asesor Kompetensi (MET) aktif dari BNSP.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid-4">
          ${db.assessors.map(a => `
            <div class="gallery-card">
              <div class="gallery-thumb" style="aspect-ratio:1/1;"><img src="${a.img}" alt="${a.name}"></div>
              <div class="gallery-body">
                <span class="badge badge-navy" style="margin-bottom:8px;">${a.status}</span>
                <h4>${a.name}</h4>
                <p style="font-weight:700;color:var(--blue);margin-bottom:4px;">${a.reg}</p>
                <p>${a.spec}</p>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderSchedulesPage(db) {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">KALENDER ASESMEN</span>
        <h1>Jadwal Uji Kompetensi Terbuka</h1>
        <p>Pilih jadwal pelaksanaan asesmen yang masih tersedia kuotanya dan lakukan pendaftaran melalui akun Pemohon.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Tanggal & Waktu</th><th>Skema Sertifikasi</th><th>Lokasi TUK</th><th>Kuota</th><th>Status</th><th>Aksi</th></tr>
            </thead>
            <tbody>
              ${db.schedules.map(sc => {
                const scheme = getSchemeById(db, sc.schemeId);
                return `
                  <tr>
                    <td><strong>${sc.date}</strong><div style="font-size:12px;color:var(--text-muted);">${sc.time}</div></td>
                    <td><strong>${scheme.name}</strong><div style="font-size:12px;color:var(--blue);">${scheme.code}</div></td>
                    <td>${sc.tuk}</td>
                    <td><strong>${sc.filled} / ${sc.quota}</strong></td>
                    <td><span class="badge ${statusBadgeClass(sc.status)}">${sc.status}</span></td>
                    <td>${sc.status === 'Dibuka' ? `<a href="#/register" class="btn btn-orange btn-sm">Daftar</a>` : `<span style="color:var(--text-muted);font-size:12.5px;">Kuota Penuh</span>`}</td>
                  </tr>
                `;
              }).join('')}
            </tbody>
          </table>
        </div>
      </div>
    </section>
  `;
}

export function renderVerifyPage() {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">VERIFIKASI RESMI</span>
        <h1>Cek Status Permohonan & Validasi Sertifikat</h1>
        <p>Masukkan ID Izin permohonan atau Nomor Sertifikat untuk memastikan keaslian sertifikat kompetensi fotografi studio.</p>
      </div>
    </section>
    <section class="section">
      <div class="container" style="max-width:780px;">
        <div class="card">
          <form id="page-verify-form" style="display:flex;gap:12px;flex-wrap:wrap;">
            <input type="text" id="page-verify-input" class="input" style="flex:1;min-width:240px;" placeholder="Contoh: IZN-2026-0101 atau SKK-FOTO-2026-00891" required>
            <button type="submit" class="btn btn-orange">Cari Data</button>
          </form>
          <div style="font-size:12.5px;color:var(--text-muted);margin-top:10px;">
            Coba ID uji: <strong>IZN-2026-0101</strong> (Kompeten), <strong>IZN-2026-0102</strong> (Tagih), atau <strong>IZN-2026-0105</strong> (Assesment).
          </div>
          <div id="page-verify-result" style="margin-top:24px;"></div>
        </div>
      </div>
    </section>
  `;
}

export function renderNewsPage(db) {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">BERITA & GALERI</span>
        <h1>Dokumentasi & Pengumuman Resmi LSP</h1>
        <p>Informasi jadwal batch terbaru, surveilan TUK, serta kegiatan uji kompetensi fotografi studio.</p>
      </div>
    </section>
    <section class="section">
      <div class="container">
        <div class="grid-3">
          ${db.articles.map(a => `
            <article class="gallery-card">
              <div class="gallery-thumb"><img src="${a.img}" alt="${a.title}"></div>
              <div class="gallery-body">
                <span class="badge badge-sky" style="margin-bottom:8px;">${a.category}</span>
                <h4>${a.title}</h4>
                <p style="margin-top:6px;">${a.date} · ${a.excerpt}</p>
              </div>
            </article>
          `).join('')}
        </div>
      </div>
    </section>
  `;
}

export function renderContactPage() {
  return `
    <section class="page-hero">
      <div class="container">
        <span class="kicker kicker-orange">KONTAK & BANDING ASESMEN</span>
        <h1>Hubungi Sekretariat & Layanan Pengaduan</h1>
        <p>Sampaikan pertanyaan, keluhan layanan, atau pengajuan banding hasil asesmen secara resmi dan terdokumentasi.</p>
      </div>
    </section>
    <section class="section">
      <div class="container faq-split">
        <div class="card">
          <h3 style="font-size:22px;font-weight:800;margin-bottom:18px;">Formulir Pengaduan / Banding Hasil Asesmen</h3>
          <form id="contact-complaint-form" class="form-grid">
            <div class="form-group"><label>Nama Lengkap</label><input class="input" required placeholder="Nama pemohon / instansi"></div>
            <div class="form-group"><label>Email / WhatsApp</label><input class="input" required placeholder="0812... / email@domain.id"></div>
            <div class="form-group">
              <label>Kategori Layanan</label>
              <select class="input">
                <option>Pertanyaan Umum Sertifikasi</option>
                <option>Pengaduan Layanan / SLA</option>
                <option>Banding Hasil Asesmen (FR.AK.04)</option>
              </select>
            </div>
            <div class="form-group"><label>ID Izin (Opsional)</label><input class="input" placeholder="IZN-2026-xxxx"></div>
            <div class="form-group full"><label>Uraian Pesan / Alasan Banding</label><textarea class="input" required placeholder="Tuliskan detail laporan atau alasan banding secara objektif..."></textarea></div>
            <div class="form-group full"><button type="submit" class="btn btn-orange">Kirim Laporan Resmi</button></div>
          </form>
        </div>

        <div class="dark-cta-card">
          <span class="badge badge-orange" style="margin-bottom:14px;">SEKRETARIAT RESMI</span>
          <h3>NusaXNine LSP Fotografi</h3>
          <p style="margin-bottom:20px;">Gedung NusaXNine Creative Hub Lt. 3, Jl. Kebon Kacang Raya, Tanah Abang, Jakarta Pusat 10240.</p>
          <ul class="check-list" style="color:var(--white);">
            <li><span class="check-dot orange">${ICONS.check}</span> Telp: (021) 5089-2409</li>
            <li><span class="check-dot orange">${ICONS.check}</span> WhatsApp: +62 812-2409-2026</li>
            <li><span class="check-dot orange">${ICONS.check}</span> Email: sekretariat@nusaxninelsp.id</li>
          </ul>
          <a href="#/alur" class="btn btn-outline-light btn-block">Pelajari Kebijakan SLA</a>
        </div>
      </div>
    </section>
  `;
}

function buildVerifyResultHtml(db, query) {
  const q = query.trim().toLowerCase();
  const found = db.applications.find(a =>
    a.permitId.toLowerCase() === q ||
    (a.certNumber && a.certNumber.toLowerCase() === q)
  );
  if (!found) {
    return `<div class="card" style="border-color:var(--orange);color:var(--navy);">Data dengan ID <strong>${query}</strong> tidak ditemukan dalam registri LSP.</div>`;
  }
  const scheme = getSchemeById(db, found.schemeId);
  return `
    <div class="card" style="border:2px solid var(--navy);text-align:left;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap;">
        <span class="badge badge-navy">${found.permitId}</span>
        <span class="badge ${statusBadgeClass(found.status)}">Status: ${found.status}</span>
      </div>
      <h4 style="font-size:19px;font-weight:800;">${found.fullname}</h4>
      <p style="font-size:13.5px;color:var(--text-muted);margin-bottom:10px;">Skema: <strong>${scheme.name}</strong> (${scheme.code})</p>
      <div style="font-size:13px;display:grid;gap:4px;">
        <div>Nomor Sertifikat: <strong>${found.certNumber || 'Belum terbit (dalam proses)'}</strong></div>
        <div>TUK / Asesor: <strong>${found.tuk}</strong> ${found.assessor ? `· ${found.assessor}` : ''}</div>
        <div>Keterangan Progres: <strong>${found.deadline}</strong></div>
      </div>
    </div>
  `;
}

export function bindPublicEvents(db) {
  document.querySelectorAll('[data-faq-toggle]').forEach(btn => {
    btn.onclick = () => btn.parentElement.classList.toggle('open');
  });

  const filterWrap = document.getElementById('home-gallery-filters');
  if (filterWrap) {
    filterWrap.querySelectorAll('.pill-btn').forEach(btn => {
      btn.onclick = () => {
        filterWrap.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const cat = btn.dataset.filter;
        document.querySelectorAll('#home-gallery-grid .gallery-card').forEach(card => {
          card.style.display = (cat === 'Semua' || card.dataset.category === cat) ? 'block' : 'none';
        });
      };
    });
  }

  const bindVerify = (formId, inputId, resId) => {
    const form = document.getElementById(formId);
    if (!form) return;
    form.onsubmit = e => {
      e.preventDefault();
      const val = document.getElementById(inputId).value;
      document.getElementById(resId).innerHTML = buildVerifyResultHtml(db, val);
    };
  };
  bindVerify('home-verify-form', 'home-verify-input', 'home-verify-result');
  bindVerify('page-verify-form', 'page-verify-input', 'page-verify-result');

  const contactForm = document.getElementById('contact-complaint-form');
  if (contactForm) {
    contactForm.onsubmit = e => {
      e.preventDefault();
      contactForm.reset();
      showToast('Laporan / pengajuan banding resmi telah tercatat. Tim SLA akan menghubungi dalam 1x24 jam.');
    };
  }
}