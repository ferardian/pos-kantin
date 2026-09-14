import os
import sys
from reportlab.lib.pagesizes import A4
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate, Paragraph, Spacer, Table, TableStyle, PageBreak, KeepTogether, HRFlowable
)
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.units import cm, mm
from reportlab.pdfgen import canvas

class NumberedCanvas(canvas.Canvas):
    def __init__(self, *args, **kwargs):
        super(NumberedCanvas, self).__init__(*args, **kwargs)
        self._saved_page_states = []

    def showPage(self):
        self._saved_page_states.append(dict(self.__dict__))
        self._startPage()

    def save(self):
        num_pages = len(self._saved_page_states)
        for state in self._saved_page_states:
            self.__dict__.update(state)
            self.draw_page_decorations(num_pages)
            super(NumberedCanvas, self).showPage()
        super(NumberedCanvas, self).save()

    def draw_page_decorations(self, page_count):
        if self._pageNumber == 1:
            return  # Skip cover page

        self.saveState()
        self.setFont("Helvetica-Bold", 8)
        self.setFillColor(colors.HexColor("#64748b"))

        # Header
        self.drawString(20 * mm, 285 * mm, "PANDUAN LENGKAP INSTALASI POS LISTRIK - WINDOWS CLIENT (LARAGON)")
        self.setStrokeColor(colors.HexColor("#e2e8f0"))
        self.setLineWidth(0.75)
        self.line(20 * mm, 282 * mm, 190 * mm, 282 * mm)

        # Footer
        self.setFont("Helvetica", 8)
        self.drawString(20 * mm, 12 * mm, "Sistem POS Toko Listrik & Elektronik • Panduan Implementasi Teknisi")
        page_text = f"Halaman {self._pageNumber} dari {page_count}"
        self.drawRightString(190 * mm, 12 * mm, page_text)
        self.line(20 * mm, 16 * mm, 190 * mm, 16 * mm)

        self.restoreState()

def build_pdf(filename="PANDUAN_INSTALASI_WINDOWS_POS_LISTRIK.pdf"):
    doc = SimpleDocTemplate(
        filename,
        pagesize=A4,
        leftMargin=20 * mm,
        rightMargin=20 * mm,
        topMargin=22 * mm,
        bottomMargin=22 * mm
    )

    styles = getSampleStyleSheet()

    # Custom Color Palette
    PRIMARY = colors.HexColor("#0f172a")    # Slate 900
    ACCENT = colors.HexColor("#d97706")     # Amber 600
    ACCENT_BG = colors.HexColor("#fef3c7")  # Amber 100
    DARK_BG = colors.HexColor("#1e293b")    # Slate 800
    LIGHT_BG = colors.HexColor("#f8fafc")   # Slate 50
    BORDER_CLR = colors.HexColor("#e2e8f0") # Slate 200
    TEXT_MAIN = colors.HexColor("#1e293b")
    TEXT_MUTED = colors.HexColor("#64748b")
    SUCCESS_BG = colors.HexColor("#ecfdf5")
    SUCCESS_BORDER = colors.HexColor("#a7f3d0")

    # Typography Styles
    title_style = ParagraphStyle(
        'CoverTitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=24,
        leading=30,
        textColor=PRIMARY,
        alignment=0
    )

    subtitle_style = ParagraphStyle(
        'CoverSubtitle',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=12,
        leading=16,
        textColor=ACCENT,
        alignment=0
    )

    h1_style = ParagraphStyle(
        'SectionH1',
        parent=styles['Heading1'],
        fontName='Helvetica-Bold',
        fontSize=14,
        leading=18,
        textColor=PRIMARY,
        spaceBefore=14,
        spaceAfter=6,
        keepWithNext=True
    )

    h2_style = ParagraphStyle(
        'SectionH2',
        parent=styles['Heading2'],
        fontName='Helvetica-Bold',
        fontSize=11,
        leading=15,
        textColor=colors.HexColor("#b45309"),
        spaceBefore=10,
        spaceAfter=4,
        keepWithNext=True
    )

    body_style = ParagraphStyle(
        'BodyDark',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=9.5,
        leading=13.5,
        textColor=TEXT_MAIN,
        spaceAfter=6
    )

    bullet_style = ParagraphStyle(
        'BulletDark',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=9,
        leading=13,
        textColor=TEXT_MAIN,
        leftIndent=14,
        spaceAfter=3
    )

    code_style = ParagraphStyle(
        'CodeSnippet',
        parent=styles['Normal'],
        fontName='Courier',
        fontSize=8.5,
        leading=11.5,
        textColor=colors.HexColor("#f8fafc")
    )

    box_text = ParagraphStyle(
        'BoxText',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=9,
        leading=13,
        textColor=TEXT_MAIN
    )

    table_header_style = ParagraphStyle(
        'TableHeader',
        parent=styles['Normal'],
        fontName='Helvetica-Bold',
        fontSize=8.5,
        leading=11,
        textColor=colors.white
    )

    table_cell_style = ParagraphStyle(
        'TableCell',
        parent=styles['Normal'],
        fontName='Helvetica',
        fontSize=8.5,
        leading=11.5,
        textColor=TEXT_MAIN
    )

    story = []

    def make_code_box(code_text):
        escaped = code_text.replace('&', '&amp;').replace('<', '&lt;').replace('>', '&gt;').replace('\n', '<br/>')
        p = Paragraph(escaped, code_style)
        t = Table([[p]], colWidths=[170 * mm])
        t.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), DARK_BG),
            ('PADDING', (0, 0), (-1, -1), 7),
            ('TOPPADDING', (0, 0), (-1, -1), 6),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 6),
            ('CORNERPAD', (0, 0), (-1, -1), 4),
            ('ROUNDEDCORNERS', [4, 4, 4, 4]),
        ]))
        return t

    def make_callout(text, title="PENTING / PERHATIAN", bg=ACCENT_BG, border=ACCENT):
        p_title = Paragraph(f"<b>⚠️ {title}</b>", ParagraphStyle('CTitle', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=9, textColor=colors.HexColor("#92400e"), spaceAfter=3))
        p_desc = Paragraph(text, box_text)
        t = Table([[ [p_title, p_desc] ]], colWidths=[170 * mm])
        t.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), bg),
            ('BOX', (0, 0), (-1, -1), 1, border),
            ('PADDING', (0, 0), (-1, -1), 8),
            ('ROUNDEDCORNERS', [4, 4, 4, 4]),
        ]))
        return t

    def make_success_box(text, title="BERHASIL"):
        p_title = Paragraph(f"<b>✅ {title}</b>", ParagraphStyle('STitle', parent=styles['Normal'], fontName='Helvetica-Bold', fontSize=9, textColor=colors.HexColor("#065f46"), spaceAfter=3))
        p_desc = Paragraph(text, box_text)
        t = Table([[ [p_title, p_desc] ]], colWidths=[170 * mm])
        t.setStyle(TableStyle([
            ('BACKGROUND', (0, 0), (-1, -1), SUCCESS_BG),
            ('BOX', (0, 0), (-1, -1), 1, SUCCESS_BORDER),
            ('PADDING', (0, 0), (-1, -1), 8),
            ('ROUNDEDCORNERS', [4, 4, 4, 4]),
        ]))
        return t

    # ==================== COVER PAGE ====================
    story.append(Spacer(1, 15 * mm))
    story.append(Paragraph("SOP & PANDUAN TEKNIS DEPLOYMENT", subtitle_style))
    story.append(Spacer(1, 3 * mm))
    story.append(Paragraph("Instalasi & Konfigurasi Sistem POS Listrik pada Client Windows 10/11", title_style))
    story.append(Spacer(1, 4 * mm))
    story.append(HRFlowable(width="100%", thickness=3, color=ACCENT, spaceAfter=15, spaceBefore=5))

    meta_data = [
        [Paragraph("<b>Target Sistem Operasi</b>", table_cell_style), Paragraph(": Windows 10 & Windows 11 (64-bit)", table_cell_style)],
        [Paragraph("<b>Web Server & Database</b>", table_cell_style), Paragraph(": Laragon (Apache 2.4, MySQL 8.0 / MariaDB 10+)", table_cell_style)],
        [Paragraph("<b>Backend Engine</b>", table_cell_style), Paragraph(": PHP 8.4 (Thread Safe) + Laravel 11 Framework", table_cell_style)],
        [Paragraph("<b>Frontend Engine</b>", table_cell_style), Paragraph(": Node.js 22 LTS + Vue 3 + Inertia.js + Vite", table_cell_style)],
        [Paragraph("<b>Target Pengguna</b>", table_cell_style), Paragraph(": Tim IT, Teknisi, & Implementator Lapangan", table_cell_style)],
    ]
    t_meta = Table(meta_data, colWidths=[55 * mm, 115 * mm])
    t_meta.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, -1), LIGHT_BG),
        ('BOX', (0, 0), (-1, -1), 1, BORDER_CLR),
        ('PADDING', (0, 0), (-1, -1), 6),
        ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
    ]))
    story.append(t_meta)
    story.append(Spacer(1, 12 * mm))

    # Ringkasan Eksekutif
    p_summary = """
    Dokumen ini disusun sebagai panduan resmi dan standar operasional prosedur (SOP) untuk melakukan instalasi sistem 
    <b>POS Toko Listrik & Elektronik</b> dari awal <i>(from scratch)</i> pada komputer kasir/server client berbasis OS Windows. 
    Seluruh tahapan instalasi mulai dari penyediaan software dependensi, konfigurasi Laragon, penanganan kendala umum (troubleshooting), 
    akses multi-perangkat via WiFi/LAN, hingga otomatisasi backup database harian berformat ZIP dibahas secara mendalam dan terstruktur.
    """
    story.append(make_callout(p_summary, title="RINGKASAN PANDUAN IMPLEMENTASI", bg=LIGHT_BG, border=BORDER_CLR))
    story.append(Spacer(1, 15 * mm))

    # Daftar Isi Singkat
    toc_data = [
        [Paragraph("<b>Bab</b>", table_header_style), Paragraph("<b>Topik Pembahasan</b>", table_header_style), Paragraph("<b>Fokus Utama</b>", table_header_style)],
        [Paragraph("Bab 1", table_cell_style), Paragraph("Download & Persiapan Master Installer", table_cell_style), Paragraph("Laragon, VC++ 2022, PHP 8.4, Node 22, Git", table_cell_style)],
        [Paragraph("Bab 2", table_cell_style), Paragraph("Konfigurasi Environment Server Laragon", table_cell_style), Paragraph("Setup PHP 8.4, Patch DLL Apache, Fix Node Path", table_cell_style)],
        [Paragraph("Bab 3", table_cell_style), Paragraph("Deployment Source Code & Database", table_cell_style), Paragraph("Git Clone, .env, Composer, Migration, Vite Build", table_cell_style)],
        [Paragraph("Bab 4", table_cell_style), Paragraph("Akses Sistem & Multi-Device Toko (LAN/WiFi)", table_cell_style), Paragraph("Virtual Host, Mode Sales HP, Shortcut Desktop", table_cell_style)],
        [Paragraph("Bab 5", table_cell_style), Paragraph("Solusi & Troubleshooting Kendala Windows", table_cell_style), Paragraph("nghttp2 error, VCRUNTIME, syntax error, SSL fix", table_cell_style)],
        [Paragraph("Bab 6", table_cell_style), Paragraph("Otomatisasi Backup Harian (ZIP + Scheduler)", table_cell_style), Paragraph("Script .bat, Task Scheduler, Log, Notifikasi", table_cell_style)],
    ]
    t_toc = Table(toc_data, colWidths=[20 * mm, 80 * mm, 70 * mm])
    t_toc.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PRIMARY),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, LIGHT_BG]),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_CLR),
        ('PADDING', (0, 0), (-1, -1), 5),
    ]))
    story.append(t_toc)

    story.append(PageBreak())

    # ==================== BAB 1 ====================
    story.append(Paragraph("BAB 1: DOWNLOAD & PERSIAPAN SOFTWARE PENDUKUNG", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))
    story.append(Paragraph("Sebelum memulai instalasi sistem POS, pastikan 5 software berikut telah diunduh dan disiapkan di komputer client:", body_style))

    soft_table = [
        [Paragraph("<b>No</b>", table_header_style), Paragraph("<b>Software</b>", table_header_style), Paragraph("<b>Versi Wajib</b>", table_header_style), Paragraph("<b>Link Download Resmi & Keterangan</b>", table_header_style)],
        [
            Paragraph("1", table_cell_style),
            Paragraph("<b>Laragon Full</b>", table_cell_style),
            Paragraph("v6.0+ (64-bit)", table_cell_style),
            Paragraph("<b>laragon.org/download</b><br/>Pilih installer <i>Laragon Full (64-bit)</i>.", table_cell_style)
        ],
        [
            Paragraph("2", table_cell_style),
            Paragraph("<b>MS Visual C++</b>", table_cell_style),
            Paragraph("2015-2022 x64", table_cell_style),
            Paragraph("<b>aka.ms/vs/17/release/vc_redist.x64.exe</b><br/>Wajib diinstall untuk mencegah error VCRUNTIME140.dll.", table_cell_style)
        ],
        [
            Paragraph("3", table_cell_style),
            Paragraph("<b>PHP 8.4 Windows</b>", table_cell_style),
            Paragraph("VS16 x64 Thread Safe", table_cell_style),
            Paragraph("<b>windows.php.net/download</b><br/>Wajib pilih <i>VS16 x64 Thread Safe (Zip)</i>.", table_cell_style)
        ],
        [
            Paragraph("4", table_cell_style),
            Paragraph("<b>Node.js LTS</b>", table_cell_style),
            Paragraph("v22.x LTS x64", table_cell_style),
            Paragraph("<b>nodejs.org/en/download</b><br/>Pilih <i>Windows Installer (.msi) 64-bit</i>.", table_cell_style)
        ],
        [
            Paragraph("5", table_cell_style),
            Paragraph("<b>Git for Windows</b>", table_cell_style),
            Paragraph("v2.40+ x64", table_cell_style),
            Paragraph("<b>git-scm.com/download/win</b><br/>Untuk proses clone dan update source code.", table_cell_style)
        ],
    ]
    t_soft = Table(soft_table, colWidths=[8 * mm, 32 * mm, 30 * mm, 100 * mm])
    t_soft.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PRIMARY),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, LIGHT_BG]),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_CLR),
        ('PADDING', (0, 0), (-1, -1), 5),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))
    story.append(t_soft)
    story.append(Spacer(1, 4 * mm))

    # ==================== BAB 2 ====================
    story.append(Paragraph("BAB 2: SETUP LINGKUNGAN WEB SERVER LARAGON", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))

    story.append(Paragraph("<b>2.1 Ekstrak & Konfigurasi PHP 8.4 di Laragon</b>", h2_style))
    story.append(Paragraph("1. Ekstrak file zip PHP 8.4 ke dalam folder berikut:", bullet_style))
    story.append(make_code_box(r"C:\laragon\bin\php\php-8.4.x-Win32-vs16-x64"))
    story.append(Paragraph("<i>Catatan: Pastikan file `php.exe` berada langsung di dalam folder tersebut (bukan di dalam sub-folder hasil ekstrak ganda).</i>", bullet_style))

    story.append(Paragraph("<b>2.2 Mengatasi Bentrok DLL Apache (Penting!)</b>", h2_style))
    story.append(Paragraph("Agar Apache bawaan Laragon tidak error saat membaca PHP baru, lakukan patch DLL:", bullet_style))
    story.append(Paragraph("1. Copy file <b>`nghttp2.dll`</b> dari: <font face='Courier'>C:\\laragon\\bin\\php\\php-8.4.x-Win32-vs16-x64\\nghttp2.dll</font>", bullet_style))
    story.append(Paragraph("2. Paste dan Replace ke folder bin Apache: <font face='Courier'>C:\\laragon\\bin\\apache\\httpd-2.4.x-win64-VS16\\bin\\</font>", bullet_style))

    story.append(Paragraph("<b>2.3 Membersihkan Node.js Lama di Laragon</b>", h2_style))
    story.append(Paragraph("Laragon secara default memiliki Node.js v18 bawaan di foldernya. Agar terminal Laragon membaca Node.js v22 yang baru diinstall:", bullet_style))
    story.append(Paragraph("1. Buka folder: <font face='Courier'>C:\\laragon\\bin\\nodejs\\</font>", bullet_style))
    story.append(Paragraph("2. <b>Hapus atau Rename</b> folder <font face='Courier'>node-v18.x...</font> yang ada di dalamnya.", bullet_style))

    story.append(Paragraph("<b>2.4 Pengaturan Otomatis Laragon</b>", h2_style))
    story.append(Paragraph("1. Buka aplikasi <b>Laragon</b> ➜ Klik ikon <b>Gerigi (Settings)</b> di pojok kanan atas.", bullet_style))
    story.append(Paragraph("2. Centang: <b>[✓] Run Laragon when Windows starts</b>", bullet_style))
    story.append(Paragraph("3. Centang: <b>[✓] Start All automatically</b>", bullet_style))
    story.append(Paragraph("4. Pada <b>Hostname format</b>, ubah dari `{name}.test` menjadi: <b>`{name}.local`</b>", bullet_style))
    story.append(Paragraph("5. Klik Kanan di Laragon ➜ <b>PHP ➜ Version</b> ➜ Pilih <b>`php-8.4.x...`</b>", bullet_style))
    story.append(Paragraph("6. Klik tombol <b>Stop</b> lalu klik <b>Start All</b>.", bullet_style))

    story.append(PageBreak())

    # ==================== BAB 3 ====================
    story.append(Paragraph("BAB 3: DEPLOYMENT SOURCE CODE & DATABASE", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))

    story.append(Paragraph("<b>3.1 Clone Project ke Folder WWW</b>", h2_style))
    story.append(Paragraph("Buka <b>Terminal Laragon</b> (klik tombol Terminal di Laragon), lalu jalankan:", body_style))
    story.append(make_code_box(
        "cd C:\\laragon\\www\n"
        "git clone https://github.com/ferardian/pos-listrik.git\n"
        "cd pos-listrik"
    ))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>3.2 Pembuatan Database MySQL</b>", h2_style))
    story.append(Paragraph("1. Di aplikasi Laragon, klik tombol <b>Database</b> (akan membuka HeidiSQL).", bullet_style))
    story.append(Paragraph("2. Buat database baru bernama: <b>`pos_listrik`</b> (Collation: `utf8mb4_unicode_ci`).", bullet_style))

    story.append(Paragraph("<b>3.3 Konfigurasi File Lingkungan (.env)</b>", h2_style))
    story.append(Paragraph("Copy file template konfigurasi dan sesuaikan isinya:", body_style))
    story.append(make_code_box(
        "copy .env.example .env"
    ))
    story.append(Paragraph("Buka file <font face='Courier'>.env</font> dengan Notepad, lalu pastikan parameter berikut terisi dengan benar:", body_style))
    story.append(make_code_box(
        "APP_NAME=\"POS Listrik Trisna Jaya\"\n"
        "APP_ENV=local\n"
        "APP_KEY=\n"
        "APP_DEBUG=false\n"
        "APP_URL=http://pos-listrik.local\n\n"
        "DB_CONNECTION=mysql\n"
        "DB_HOST=127.0.0.1\n"
        "DB_PORT=3306\n"
        "DB_DATABASE=pos_listrik\n"
        "DB_USERNAME=root\n"
        "DB_PASSWORD="
    ))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>3.4 Instalasi Dependensi Backend & Storage Link</b>", h2_style))
    story.append(Paragraph("Jalankan rangkaian perintah berikut di Terminal Laragon secara berurutan:", body_style))
    story.append(make_code_box(
        "composer install --optimize-autoloader --no-dev\n"
        "php artisan key:generate\n"
        "php artisan storage:link"
    ))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>3.5 Import Database & Migrasi</b>", h2_style))
    story.append(Paragraph("• <b>Opsi A (Jika ada File Dump SQL Server Lama):</b> Buka HeidiSQL ➜ Database `pos_listrik` ➜ File ➜ Load SQL file ➜ Pilih file dump ➜ Tekan F9 (Execute). Setelah selesai jalankan: <font face='Courier'>php artisan migrate</font>", bullet_style))
    story.append(Paragraph("• <b>Opsi B (Jika Database Baru/Fresh):</b> Jalankan perintah: <font face='Courier'>php artisan migrate --seed</font>", bullet_style))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>3.6 Kompilasi Aset Frontend (Vue 3 + Vite)</b>", h2_style))
    story.append(Paragraph("Jalankan kompilasi frontend agar seluruh aset termuat dengan cepat:", body_style))
    story.append(make_code_box(
        "npm install\n"
        "npm run build"
    ))

    story.append(PageBreak())

    # ==================== BAB 4 ====================
    story.append(Paragraph("BAB 4: AKSES SISTEM & PENGGUNAAN MULTI-DEVICE", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))

    story.append(Paragraph("<b>4.1 Akses di Komputer Server Kasir Utama</b>", h2_style))
    story.append(Paragraph("Karena menggunakan Apache Virtual Host di Laragon, Anda <b>tidak perlu</b> menjalankan `php artisan serve`. Cukup pastikan Laragon aktif, lalu buka browser:", body_style))
    story.append(make_code_box(
        "http://pos-listrik.local"
    ))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>4.2 Cara Membuat Shortcut Aplikasi di Desktop Windows</b>", h2_style))
    story.append(Paragraph("Agar kasir cukup mengklik ikon di Desktop layaknya aplikasi kasir native:", bullet_style))
    story.append(Paragraph("1. Buka browser Chrome / Edge di alamat: <font face='Courier'>http://pos-listrik.local</font>", bullet_style))
    story.append(Paragraph("2. Klik menu titik tiga di pojok kanan atas Chrome ➜ <b>Save and share</b> ➜ <b>Install page as app</b> (atau <i>Create Shortcut</i>).", bullet_style))
    story.append(Paragraph("3. Centang pilihan <b>'Open as window'</b> ➜ Klik <b>Create / Install</b>.", bullet_style))
    story.append(Paragraph("4. Ikon POS Listrik akan muncul di Desktop Windows dan taskbar kasir.", bullet_style))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>4.3 Akses dari HP Sales / Tablet Kasir Lapangan (Satu WiFi Toko)</b>", h2_style))
    story.append(Paragraph("Sistem ini mendukung akses multi-device secara nirkabel melalui jaringan WiFi toko:", body_style))
    story.append(Paragraph("1. Cek IP Komputer Server Kasir di CMD: ketik <font face='Courier'>ipconfig</font> (misal IP: `192.168.1.100`).", bullet_style))
    story.append(Paragraph("2. Di komputer server, jalankan:", bullet_style))
    story.append(make_code_box("php artisan serve --host=0.0.0.0 --port=8000"))
    story.append(Paragraph("3. Di HP Sales / Tablet yang terhubung ke WiFi toko, buka browser:", bullet_style))
    story.append(Paragraph("• <b>Mode Kasir POS:</b> <font face='Courier'>http://192.168.1.100:8000/pos</font>", bullet_style))
    story.append(Paragraph("• <b>Mode Sales Lapangan:</b> <font face='Courier'>http://192.168.1.100:8000/mobile-sales</font>", bullet_style))
    story.append(Spacer(1, 3 * mm))

    # ==================== BAB 5 ====================
    story.append(Paragraph("BAB 5: SOLUSI TROUBLESHOOTING KENDALA UMUM", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))

    faq_data = [
        [Paragraph("<b>Pesan Kendala / Error</b>", table_header_style), Paragraph("<b>Penyebab & Solusi Penanganan Cepat</b>", table_header_style)],
        [
            Paragraph("<b>httpd.exe Entry Point Not Found (nghttp2.dll)</b>", table_cell_style),
            Paragraph("Bentrok DLL antara Apache bawaan dan PHP baru.<br/><b>Solusi:</b> Copy <font face='Courier'>nghttp2.dll</font> dari folder PHP 8.4 ke folder <font face='Courier'>apache/bin/</font> lalu restart Laragon.", table_cell_style)
        ],
        [
            Paragraph("<b>VCRUNTIME140.dll is not compatible</b>", table_cell_style),
            Paragraph("Windows belum memiliki runtime C++ 2022.<br/><b>Solusi:</b> Install file resmi <b>vc_redist.x64.exe</b> dari Microsoft lalu restart Laragon.", table_cell_style)
        ],
        [
            Paragraph("<b>Parse error: unexpected token '{' in Request.php</b>", table_cell_style),
            Paragraph("Terminal masih membaca PHP 8.2 sedangkan Symfony butuh PHP 8.4.<br/><b>Solusi:</b> Switch ke PHP 8.4 di Laragon, tutup terminal dan buka terminal baru.", table_cell_style)
        ],
        [
            Paragraph("<b>SyntaxError: node:util styleText</b>", table_cell_style),
            Paragraph("Node.js yang aktif masih versi lama (v18).<br/><b>Solusi:</b> Hapus folder node-v18 di <font face='Courier'>C:\\laragon\\bin\\nodejs\\</font> agar terminal membaca Node.js v22 LTS.", table_cell_style)
        ],
        [
            Paragraph("<b>ERR_CONNECTION_CLOSED / Unsupported SSL</b>", table_cell_style),
            Paragraph("Browser mencoba akses via HTTPS pada server HTTP lokal.<br/><b>Solusi:</b> Pastikan di file <font face='Courier'>.env</font> nilainya <font face='Courier'>APP_URL=http://pos-listrik.local</font> lalu jalankan <font face='Courier'>php artisan config:clear</font>.", table_cell_style)
        ],
    ]
    t_faq = Table(faq_data, colWidths=[65 * mm, 105 * mm])
    t_faq.setStyle(TableStyle([
        ('BACKGROUND', (0, 0), (-1, 0), PRIMARY),
        ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, LIGHT_BG]),
        ('GRID', (0, 0), (-1, -1), 0.5, BORDER_CLR),
        ('PADDING', (0, 0), (-1, -1), 5),
        ('VALIGN', (0, 0), (-1, -1), 'TOP'),
    ]))
    story.append(t_faq)

    story.append(PageBreak())

    # ==================== BAB 6 ====================
    story.append(Paragraph("BAB 6: OTOMATISASI BACKUP HARIAN (ZIP + SCHEDULER)", h1_style))
    story.append(HRFlowable(width="100%", thickness=1, color=PRIMARY, spaceAfter=8, spaceBefore=2))

    story.append(Paragraph("<b>6.1 Source Code Script Batch (backup_pos.bat)</b>", h2_style))
    story.append(Paragraph("Buat file baru bernama <b>`backup_pos.bat`</b> di folder <font face='Courier'>C:\\laragon\\backup_pos.bat</font>, lalu isi dengan script berikut:", body_style))

    bat_script = (
        "@echo off\n"
        "setlocal\n\n"
        ":: 1. Format Tanggal & Jam (PowerShell Standar Windows 11)\n"
        "for /f \"tokens=*\" %%a in ('powershell -Command \"Get-Date -Format 'yyyy-MM-dd_HH-mm'\"') do set TIMESTAMP=%%a\n"
        "for /f \"tokens=*\" %%a in ('powershell -Command \"Get-Date -Format 'yyyy-MM-dd HH:mm:ss'\"') do set LOG_TIME=%%a\n\n"
        ":: 2. Otomatis Deteksi Lokasi mysqldump Laragon\n"
        "for /d %%i in (C:\\laragon\\bin\\mysql\\*) do set MYSQLDUMP=%%i\\bin\\mysqldump.exe\n\n"
        ":: 3. Konfigurasi Folder & Database\n"
        "set BACKUP_DIR=D:\\BACKUP_POS\n"
        "set DB_NAME=pos_listrik\n"
        "set DB_USER=root\n"
        "set TEMP_SQL=%BACKUP_DIR%\\temp_%DB_NAME%_%TIMESTAMP%.sql\n"
        "set FINAL_ZIP=%BACKUP_DIR%\\backup_%DB_NAME%_%TIMESTAMP%.zip\n"
        "set LOG_FILE=%BACKUP_DIR%\\backup_log.txt\n\n"
        "if not exist \"%BACKUP_DIR%\" mkdir \"%BACKUP_DIR%\"\n\n"
        ":: 4. Eksekusi Dump Database\n"
        "\"%MYSQLDUMP%\" -u %DB_USER% %DB_NAME% > \"%TEMP_SQL%\"\n\n"
        ":: 5. Kompresi ke ZIP (Hemat Storage s/d 90%)\n"
        "powershell -Command \"Compress-Archive -Path '%TEMP_SQL%' -DestinationPath '%FINAL_ZIP%' -CompressionLevel Optimal -Force\"\n\n"
        ":: 6. Bersihkan File SQL Mentah\n"
        "if exist \"%TEMP_SQL%\" del \"%TEMP_SQL%\"\n\n"
        ":: 7. Catat ke File Log Riwayat\n"
        "echo [%LOG_TIME%] SUKSES: Backup tersimpan di backup_%DB_NAME%_%TIMESTAMP%.zip >> \"%LOG_FILE%\"\n\n"
        ":: 8. Munculkan Notifikasi Balon Windows\n"
        "powershell -Command \"[void][reflection.assembly]::loadwithpartialname('System.Windows.Forms'); $notify = new-object system.windows.forms.notifyicon; $notify.icon = [System.Drawing.SystemIcons]::Information; $notify.visible = $true; $notify.showballoontip(5000, 'POS Listrik - Auto Backup', 'Database berhasil di-backup dan dikompres ke ZIP.', [System.Windows.Forms.ToolTipIcon]::Info); Start-Sleep -Seconds 2; $notify.dispose()\" > $null 2>&1\n\n"
        ":: 9. Hapus file backup yang umurnya lebih dari 30 hari\n"
        "forfiles /p \"%BACKUP_DIR%\" /s /m *.zip /d -30 /c \"cmd /c del @path\" 2>nul"
    )
    story.append(make_code_box(bat_script))
    story.append(Spacer(1, 4 * mm))

    story.append(Paragraph("<b>6.2 Penjadwalan di Windows Task Scheduler</b>", h2_style))
    story.append(Paragraph("1. Tekan Start ➜ Ketik <b>Task Scheduler</b> ➜ Buka.", bullet_style))
    story.append(Paragraph("2. Klik <b>Create Basic Task...</b> di panel kanan.", bullet_style))
    story.append(Paragraph("3. Beri nama: <b>Auto Backup POS Listrik</b> ➜ Pilih Trigger: <b>Daily (Jam 22:00 malam)</b>.", bullet_style))
    story.append(Paragraph("4. Pada Action, pilih <b>Start a program</b> ➜ Browse file <font face='Courier'>C:\\laragon\\backup_pos.bat</font>.", bullet_style))
    story.append(Paragraph("5. Di kolom <i>Start in (optional)</i>, isi: <font face='Courier'>C:\\laragon</font>.", bullet_style))
    story.append(Paragraph("6. Pada Properties Task: Di tab <i>Settings</i>, centang <b>'Run task as soon as possible after a scheduled start is missed'</b> *(agar jika PC mati saat jam 22.00, sistem otomatis backup saat PC dinyalakan besoknya)*.", bullet_style))
    story.append(Spacer(1, 3 * mm))

    story.append(Paragraph("<b>6.3 Integrasi Cloud Sync (Google Drive / OneDrive)</b>", h2_style))
    story.append(Paragraph("Untuk perlindungan ekstra terhadap kebakaran, pencurian, atau kerusakan fisik PC:", body_style))
    story.append(Paragraph("• Install <b>Google Drive for Desktop</b> pada PC kasir.", bullet_style))
    story.append(Paragraph("• Ubah parameter di script: <font face='Courier'>set BACKUP_DIR=G:\\My Drive\\BACKUP_POS</font>", bullet_style))
    story.append(Paragraph("• File ZIP backup akan otomatis terunggah ke Google Drive dalam hitungan detik setiap malam.", bullet_style))
    story.append(Spacer(1, 4 * mm))

    story.append(make_success_box(
        "Seluruh prosedur instalasi, konfigurasi web server, dan sistem backup otomatis kini telah siap diimplementasikan ke seluruh client Windows toko. Simpan dokumen PDF ini sebagai panduan standar tim IT.",
        title="DOKUMEN PANDUAN LENGKAP SIAP DIGUNAKAN"
    ))

    doc.build(story, canvasmaker=NumberedCanvas)
    print(f"PDF berhasil dibuat: {filename}")

if __name__ == "__main__":
    build_pdf()
