<?php

// Tangani permintaan AJAX (simpan, hapus, update)

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if ($action != '') {
    // Bersihkan SEMUA output HTML dari index.php agar response JSON bersih
    while (ob_get_level()) {
        ob_end_clean();
    }
    // Pastikan folder upload ada
    $upload_dir = 'uploads/agenda/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // POST: Simpan agenda baru
    if ($action == 'save' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        
        $tanggal = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
        $jam     = isset($_POST['jam']) ? trim($_POST['jam']) : null;
        $tempat  = isset($_POST['tempat_kunjungan']) ? trim($_POST['tempat_kunjungan']) : '';
        $agenda  = isset($_POST['agenda']) ? trim($_POST['agenda']) : '';
        $peserta = isset($_POST['peserta']) ? trim($_POST['peserta']) : '';
        
        if (empty($tanggal) || empty($tempat) || empty($agenda) || empty($peserta)) {
            echo json_encode(array('status' => 'error', 'message' => 'Semua field wajib diisi'));
            exit;
        }
        
        $dokumen_name = null;
        if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] == 0) {
            $file = $_FILES['dokumen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png');
            
            if (!in_array($ext, $allowed)) {
                echo json_encode(array('status' => 'error', 'message' => 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed)));
                exit;
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(array('status' => 'error', 'message' => 'Ukuran file maksimal 10MB'));
                exit;
            }
            $dokumen_name = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $dokumen_name)) {
                echo json_encode(array('status' => 'error', 'message' => 'Gagal mengupload file'));
                exit;
            }
        }
        
        try {
            $sql = "INSERT INTO tagenda (tanggal, jam, tempat_kunjungan, agenda, peserta, dokumen, created_by, created_at) 
                    VALUES (:tanggal, :jam, :tempat, :agenda, :peserta, :dokumen, :created_by, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':tanggal', $tanggal);
            $jam_val = (!empty($jam)) ? $jam : null;
            $stmt->bindParam(':jam', $jam_val);
            $stmt->bindParam(':tempat', $tempat);
            $stmt->bindParam(':agenda', $agenda);
            $stmt->bindParam(':peserta', $peserta);
            $stmt->bindParam(':dokumen', $dokumen_name);
            $stmt->bindParam(':created_by', $iduser);
            $stmt->execute();
            echo json_encode(array('status' => 'success', 'message' => 'Agenda berhasil disimpan'));
        } catch (PDOException $e) {
            echo json_encode(array('status' => 'error', 'message' => 'Gagal menyimpan agenda'));
        }
        exit;
    }

    // POST: Hapus agenda
    if ($action == 'delete' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id <= 0) {
            echo json_encode(array('status' => 'error', 'message' => 'ID tidak valid'));
            exit;
        }
        try {
            $sql = "SELECT dokumen FROM tagenda WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['dokumen'])) {
                $filepath = $upload_dir . $row['dokumen'];
                if (file_exists($filepath)) unlink($filepath);
            }
            $sql = "DELETE FROM tagenda WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(array('status' => 'success', 'message' => 'Agenda berhasil dihapus'));
        } catch (PDOException $e) {
            echo json_encode(array('status' => 'error', 'message' => 'Gagal menghapus agenda'));
        }
        exit;
    }

    // POST: Update agenda
    if ($action == 'update' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        header('Content-Type: application/json');
        $id      = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $tanggal = isset($_POST['tanggal']) ? trim($_POST['tanggal']) : '';
        $jam     = isset($_POST['jam']) ? trim($_POST['jam']) : null;
        $tempat  = isset($_POST['tempat_kunjungan']) ? trim($_POST['tempat_kunjungan']) : '';
        $agenda  = isset($_POST['agenda']) ? trim($_POST['agenda']) : '';
        $peserta = isset($_POST['peserta']) ? trim($_POST['peserta']) : '';
        
        if ($id <= 0) {
            echo json_encode(array('status' => 'error', 'message' => 'ID tidak valid'));
            exit;
        }
        if (empty($tanggal) || empty($tempat) || empty($agenda) || empty($peserta)) {
            echo json_encode(array('status' => 'error', 'message' => 'Semua field wajib diisi'));
            exit;
        }
        
        $dokumen_name = null;
        $update_dokumen = false;
        if (isset($_FILES['dokumen']) && $_FILES['dokumen']['error'] == 0) {
            $file = $_FILES['dokumen'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png');
            if (!in_array($ext, $allowed)) {
                echo json_encode(array('status' => 'error', 'message' => 'Tipe file tidak diizinkan. Gunakan: ' . implode(', ', $allowed)));
                exit;
            }
            if ($file['size'] > 10 * 1024 * 1024) {
                echo json_encode(array('status' => 'error', 'message' => 'Ukuran file maksimal 10MB'));
                exit;
            }
            $dokumen_name = date('YmdHis') . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
            if (!move_uploaded_file($file['tmp_name'], $upload_dir . $dokumen_name)) {
                echo json_encode(array('status' => 'error', 'message' => 'Gagal mengupload file'));
                exit;
            }
            $update_dokumen = true;
            // Hapus file dokumen lama
            $sql = "SELECT dokumen FROM tagenda WHERE id = :id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $old = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($old && !empty($old['dokumen'])) {
                $oldpath = $upload_dir . $old['dokumen'];
                if (file_exists($oldpath)) unlink($oldpath);
            }
        }
        
        try {
            if ($update_dokumen) {
                $sql = "UPDATE tagenda SET tanggal = :tanggal, jam = :jam, tempat_kunjungan = :tempat, agenda = :agenda, peserta = :peserta, dokumen = :dokumen WHERE id = :id";
            } else {
                $sql = "UPDATE tagenda SET tanggal = :tanggal, jam = :jam, tempat_kunjungan = :tempat, agenda = :agenda, peserta = :peserta WHERE id = :id";
            }
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':tanggal', $tanggal);
            $jam_val = (!empty($jam)) ? $jam : null;
            $stmt->bindParam(':jam', $jam_val);
            $stmt->bindParam(':tempat', $tempat);
            $stmt->bindParam(':agenda', $agenda);
            $stmt->bindParam(':peserta', $peserta);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            if ($update_dokumen) {
                $stmt->bindParam(':dokumen', $dokumen_name);
            }
            $stmt->execute();
            echo json_encode(array('status' => 'success', 'message' => 'Agenda berhasil diperbarui'));
        } catch (PDOException $e) {
            echo json_encode(array('status' => 'error', 'message' => 'Gagal memperbarui agenda'));
        }
        exit;
    }

    // Action tidak dikenal
    header('Content-Type: application/json');
    echo json_encode(array('status' => 'error', 'message' => 'Action tidak valid'));
    exit;
}

// Tampilan Kalender (jika bukan AJAX request)

$events = array();

// Fetch Hari Libur dari Google Calendar API (Kalender Hari Libur Indonesia)
// API ini gratis, otomatis update, dan lengkap (Islam, Hindu, Buddha, Tionghoa, Kristen)
$google_calendar_id = 'id.indonesian%23holiday%40group.v.calendar.google.com';
$google_api_key = 'AIzaSyBNlYH01_9Hc5S1J9vuFmu2nUqBZJNAXxs'; // Public API key untuk kalender

$current_year = date('Y');

// Fetch untuk tahun ini dan tahun depan
$years_to_fetch = [$current_year, $current_year + 1];
foreach ($years_to_fetch as $year) {
    $api_url = "https://www.googleapis.com/calendar/v3/calendars/{$google_calendar_id}/events?"
        . "key={$google_api_key}"
        . "&timeMin={$year}-01-01T00:00:00Z"
        . "&timeMax={$year}-12-31T23:59:59Z"
        . "&singleEvents=true"
        . "&orderBy=startTime";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $title = isset($item['summary']) ? $item['summary'] : '';
                $date = isset($item['start']['date']) ? $item['start']['date'] : '';
                $desc = isset($item['description']) ? $item['description'] : '';

                if (empty($title) || empty($date)) continue;

                // Skip perayaan yang bukan hari libur nasional (hanya tampilkan yang "Hari libur nasional")
                $isLiburNasional = (strpos($desc, 'Hari libur nasional') !== false);
                $isCutiBersama = (strpos($title, 'Cuti Bersama') !== false);

                // Hanya tampilkan hari libur nasional dan cuti bersama
                if ($isLiburNasional || $isCutiBersama) {
                    $events[] = array(
                        'id' => 'holiday-' . $date . '-' . md5($title),
                        'title' => $title,
                        'start' => $date,
                        'allDay' => true,
                        'color' => $isCutiBersama ? '#f0ad4e' : '#ff6c60',
                        'textColor' => '#ffffff',
                        'editable' => false,
                        'description' => $isCutiBersama ? 'Cuti Bersama' : 'Hari Libur Nasional'
                    );
                }
            }
        }
    }
}

// Fetch Agenda Internal dari database
try {
    $sql = "SELECT id, tanggal, jam, tempat_kunjungan, agenda, peserta, dokumen, created_by, created_at FROM tagenda ORDER BY tanggal ASC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Prefix judul dengan jam jika ada
        $title = $row['agenda'];
        $jam_display = '';
        if (!empty($row['jam'])) {
            $jam_display = substr($row['jam'], 0, 5); // Format HH:MM
            $title = $jam_display . ' - ' . $title;
        }
        $events[] = array(
            'id' => 'agenda-' . $row['id'],
            'title' => $title,
            'start' => $row['tanggal'],
            'allDay' => true,
            'color' => '#27ae60',
            'textColor' => '#ffffff',
            'editable' => false,
            'description' => 'Agenda Internal',
            'agenda_id' => $row['id'],
            'jam' => $jam_display,
            'agenda_text' => $row['agenda'],
            'tempat_kunjungan' => $row['tempat_kunjungan'],
            'peserta' => $row['peserta'],
            'dokumen' => $row['dokumen'],
            'created_by' => $row['created_by'],
            'created_at' => $row['created_at']
        );
    }
} catch (PDOException $e) {
    // Tabel mungkin belum ada, abaikan
}

$events_json = json_encode($events);
?>

<!-- Muat CSS dan JS FullCalendar -->
<link href="assets/fullcalendar/fullcalendar/bootstrap-fullcalendar.css" rel="stylesheet" />
<!-- Catatan: jQuery sudah dimuat di index.php -->
<!-- <script src="js/moment.js"></script> --> <!-- Tidak diperlukan untuk FullCalendar v1.6.1 -->
<script src="js/fullcalendar.min.js"></script>

<style>
    /* Styling kustom untuk tampilan kalender */
    .fc-header-title h2 {
        font-size: 18px;
        font-weight: 700;
        color: #333;
        margin-top: 0;
    }
    .fc-event {
        font-size: 13px;
        border: none;
        padding: 2px 4px;
        cursor: pointer;
    }
    /* Event agenda: tampilan kartu */
    .fc-event.agenda-event {
        white-space: normal !important;
        line-height: 1.15;
        padding: 2px 4px;
        border-radius: 3px;
        margin-bottom: 1px;
        border: none;
        border-left: 3px solid rgba(255,255,255,0.4);
        height: auto !important;
        min-height: 0 !important;
        transition: opacity 0.15s ease;
        overflow: hidden;
    }
    .fc-event.agenda-event:hover {
        opacity: 0.85;
    }
    .fc-event.agenda-event .fc-event-inner {
        white-space: normal !important;
        height: auto !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .fc-event.agenda-event .fc-event-title {
        white-space: normal !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    .fc-event.agenda-event .evt-title {
        font-weight: 700;
        font-size: 13px;
        display: block;
        line-height: 1.15;
        margin: 0;
        padding: 0;
    }
    .fc-event.agenda-event .evt-detail {
        font-size: 12px;
        opacity: 0.9;
        display: block;
        line-height: 1.15;
        margin: 0;
        padding: 0;
    }
    .fc-event.agenda-event .evt-detail i {
        font-size: 11px;
        margin-right: 2px;
        width: 10px;
        display: inline-block;
    }
    .legend-item {
        display: inline-block;
        margin-right: 15px;
        font-size: 12px;
        color: #555;
    }
    .legend-color {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-right: 5px;
    }
    .calendar-toolbar {
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .btn-custom {
        border-radius: 20px;
        padding: 6px 15px;
        font-size: 12px;
    }
    /* Pastikan kalender memiliki tinggi minimum */
    #calendar {
        min-height: 600px;
    }

    /* Gaya Modal Agenda */
    .modal-agenda .modal-header {
        background: #27ae60;
        color: #fff;
        border-radius: 4px 4px 0 0;
    }
    .modal-agenda .modal-header .close {
        color: #fff;
        opacity: 0.8;
    }
    .modal-agenda .modal-header .close:hover {
        opacity: 1;
    }
    .modal-agenda .form-group {
        margin-bottom: 15px;
    }
    .modal-agenda .form-group label {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }
    .modal-agenda .form-control {
        border-radius: 4px;
        border: 1px solid #ddd;
        padding: 8px 12px;
    }
    .modal-agenda .form-control:focus {
        border-color: #27ae60;
        box-shadow: 0 0 0 2px rgba(39, 174, 96, 0.15);
    }

    /* Gaya baris detail (di dalam modal-agenda) */
    .modal-agenda .detail-row {
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .modal-agenda .detail-row:last-child {
        border-bottom: none;
    }
    .modal-agenda .detail-label {
        font-weight: 600;
        color: #555;
        font-size: 14px;
        text-transform: uppercase;
        margin-bottom: 3px;
    }
    .modal-agenda .detail-value {
        color: #333;
        font-size: 16px;
    }
    .btn-hapus-agenda {
        background: #e74c3c;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 8px 20px;
    }
    .btn-hapus-agenda:hover {
        background: #c0392b;
        color: #fff;
    }
</style>

<div class="row">
    <div class="col-lg-12">
        <section class="panel">
            <div class="panel-body">
                
                <!-- Area Toolbar/Header Kustom -->
                <div class="calendar-toolbar">
                    <div>
                        <h4 style="margin: 0; font-weight: bold;">Kalender Kegiatan</h4>
                        <span class="text-muted" style="font-size: 14px;">Hari libur nasional, cuti bersama, dan agenda internal</span>
                    </div>
                </div>

                <hr style="margin: 10px 0;">

                <!-- Legenda dan Kontrol -->
                <div class="row" style="margin-bottom: 20px;">
                    <div class="col-md-6">
                    </div>
                    <div class="col-md-6 text-right">
                        <div class="legend-item"><span class="legend-color" style="background-color: #ff6c60;"></span> Libur Nasional</div>
                        <div class="legend-item"><span class="legend-color" style="background-color: #f0ad4e;"></span> Cuti Bersama</div>
                        <div class="legend-item"><span class="legend-color" style="background-color: #27ae60;"></span> Agenda Internal</div>
                    </div>
                </div>

                <!-- Kontainer Kalender -->
                <div id="calendar" class="has-toolbar"></div>
            </div>
        </section>
    </div>
</div>

<!-- Modal Tambah Agenda -->
<div class="modal fade modal-agenda" id="modalTambahAgenda" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-plus-circle"></i> Tambah Agenda Internal</h4>
            </div>
            <form id="formAgenda" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="tanggal" id="agendaTanggal">
                    
                    <div class="form-group">
                        <label>Tanggal</label>
                        <input type="text" class="form-control" id="agendaTanggalDisplay" readonly style="background: #f9f9f9;">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-clock-o"></i> Jam</label>
                        <input type="time" class="form-control" name="jam" id="agendaJam" style="width:150px;">
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-map-marker"></i> Tempat Kunjungan <span style="color:red;">*</span></label>
                        <input type="text" class="form-control" name="tempat_kunjungan" id="agendaTempat" placeholder="Contoh: Kantor PT ABC, Jakarta" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-calendar-check-o"></i> Agenda <span style="color:red;">*</span></label>
                        <textarea class="form-control" name="agenda" id="agendaIsi" rows="3" placeholder="Jelaskan agenda kegiatan..." required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-users"></i> Siapa yang Terlibat <span style="color:red;">*</span></label>
                        <textarea class="form-control" name="peserta" id="agendaPeserta" rows="2" placeholder="Contoh: Budi, Ani, Pak Direktur" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fa fa-file"></i> Upload Dokumen <small class="text-muted">(opsional, maks 10MB)</small></label>
                        <input type="file" class="form-control" name="dokumen" id="agendaDokumen" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                        <small class="text-muted">Format: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, JPEG, PNG</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success" id="btnSimpanAgenda"><i class="fa fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Detail / Edit Agenda -->
<div class="modal fade modal-agenda" id="modalDetailAgenda" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="detailModalTitle"><i class="fa fa-calendar"></i> Detail Agenda</h4>
            </div>
            <form id="formEditAgenda" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action" id="editAction" value="update">
                    <input type="hidden" name="id" id="editAgendaId">
                    <input type="hidden" name="tanggal" id="editTanggal">
                    
                    <!-- Mode Lihat -->
                    <div id="viewMode">
                        <div class="detail-row">
                            <div class="detail-label"><i class="fa fa-calendar-o"></i> Tanggal</div>
                            <div class="detail-value" id="detailTanggal"></div>
                        </div>
                        <div class="detail-row" id="detailJamRow">
                            <div class="detail-label"><i class="fa fa-clock-o"></i> Jam</div>
                            <div class="detail-value" id="detailJam"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fa fa-map-marker"></i> Tempat Kunjungan</div>
                            <div class="detail-value" id="detailTempat"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fa fa-calendar-check-o"></i> Agenda</div>
                            <div class="detail-value" id="detailAgenda"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fa fa-users"></i> Yang Terlibat</div>
                            <div class="detail-value" id="detailPeserta"></div>
                        </div>
                        <div class="detail-row" id="detailDokumenRow">
                            <div class="detail-label"><i class="fa fa-file"></i> Dokumen</div>
                            <div class="detail-value" id="detailDokumen"></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label"><i class="fa fa-user"></i> Dibuat Oleh</div>
                            <div class="detail-value" id="detailCreatedBy"></div>
                        </div>
                    </div>

                    <!-- Mode Edit (sama seperti form tambah) -->
                    <div id="editMode" style="display:none;">
                        <div class="form-group">
                            <label>Tanggal</label>
                            <input type="text" class="form-control" id="editTanggalDisplay" readonly style="background: #f9f9f9;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-clock-o"></i> Jam</label>
                            <input type="time" class="form-control" name="jam" id="editJamInput" style="width:150px;">
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-map-marker"></i> Tempat Kunjungan <span style="color:red;">*</span></label>
                            <input type="text" class="form-control" name="tempat_kunjungan" id="editTempatInput" placeholder="Contoh: Kantor PT ABC, Jakarta" required>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-calendar-check-o"></i> Agenda <span style="color:red;">*</span></label>
                            <textarea class="form-control" name="agenda" id="editAgendaInput" rows="3" placeholder="Jelaskan agenda kegiatan..." required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-users"></i> Siapa yang Terlibat <span style="color:red;">*</span></label>
                            <textarea class="form-control" name="peserta" id="editPesertaInput" rows="2" placeholder="Contoh: Budi, Ani, Pak Direktur" required></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fa fa-file"></i> Upload Dokumen Baru <small class="text-muted">(opsional, maks 10MB)</small></label>
                            <div id="editDokumenLama" style="margin-bottom:8px;"></div>
                            <input type="file" class="form-control" name="dokumen" id="editDokumenInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
                            <small class="text-muted">Kosongkan jika tidak ingin mengganti dokumen</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <!-- Tombol mode lihat -->
                    <div id="viewButtons">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Tutup</button>
                        <button type="button" class="btn btn-primary" id="btnEditAgenda"><i class="fa fa-pencil"></i> Edit</button>
                        <button type="button" class="btn btn-hapus-agenda" id="btnHapusAgenda"><i class="fa fa-trash"></i> Hapus</button>
                    </div>
                    <!-- Tombol mode edit -->
                    <div id="editButtons" style="display:none;">
                        <button type="button" class="btn btn-default" id="btnBatalEdit">Batal</button>
                        <button type="submit" class="btn btn-success" id="btnSimpanEdit"><i class="fa fa-save"></i> Simpan Perubahan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        
        var dbEvents = <?php echo ($events_json) ? $events_json : '[]'; ?>;
        var currentAgendaId = null;
        var currentEventData = {};
        
        // Format tanggal Indonesia
        function formatTanggal(dateStr) {
            var bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            var hari = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            var d = new Date(dateStr + 'T00:00:00');
            return hari[d.getDay()] + ', ' + d.getDate() + ' ' + bulan[d.getMonth()] + ' ' + d.getFullYear();
        }

        // Switch ke mode lihat
        function showViewMode() {
            $('#viewMode').show();
            $('#editMode').hide();
            $('#viewButtons').show();
            $('#editButtons').hide();
            $('#detailModalTitle').html('<i class="fa fa-calendar"></i> Detail Agenda');
        }

        // Switch ke mode edit
        function showEditMode() {
            $('#viewMode').hide();
            $('#editMode').show();
            $('#viewButtons').hide();
            $('#editButtons').show();
            $('#detailModalTitle').html('<i class="fa fa-pencil"></i> Edit Agenda');

            // Isi form edit dari data event
            var dateStr = currentEventData.dateStr || '';
            $('#editTanggal').val(dateStr);
            $('#editTanggalDisplay').val(formatTanggal(dateStr));
            $('#editJamInput').val(currentEventData.jam || '');
            $('#editTempatInput').val(currentEventData.tempat || '');
            $('#editAgendaInput').val(currentEventData.agenda || '');
            $('#editPesertaInput').val(currentEventData.peserta || '');
            $('#editDokumenInput').val('');

            // Info dokumen lama
            if (currentEventData.dokumen) {
                $('#editDokumenLama').html('<small><i class="fa fa-paperclip"></i> Dokumen saat ini: <a href="uploads/agenda/' + currentEventData.dokumen + '" target="_blank">' + currentEventData.dokumen + '</a></small>');
            } else {
                $('#editDokumenLama').html('<small class="text-muted">Belum ada dokumen</small>');
            }
        }
        
        try {
            $('#calendar').fullCalendar({
                header: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                monthNames: ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'],
                monthNamesShort: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agust', 'Sep', 'Okt', 'Nov', 'Des'],
                dayNames: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
                dayNamesShort: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
                editable: false,
                droppable: false,
                events: dbEvents, 
                
                // Klik tanggal untuk tambah agenda
                dayClick: function(date, allDay, jsEvent, view) {
                    var dateStr = $.fullCalendar.formatDate(date, 'yyyy-MM-dd');
                    $('#agendaTanggal').val(dateStr);
                    $('#agendaTanggalDisplay').val(formatTanggal(dateStr));
                    // Reset form
                    $('#agendaTempat').val('');
                    $('#agendaJam').val('');
                    $('#agendaIsi').val('');
                    $('#agendaPeserta').val('');
                    $('#agendaDokumen').val('');
                    $('#modalTambahAgenda').modal('show');
                },
                
                // Klik event untuk lihat detail
                eventClick: function(event, jsEvent, view) {
                    // Jika event agenda internal, tampilkan detail
                    if (event.id && event.id.toString().indexOf('agenda-') === 0) {
                        currentAgendaId = event.agenda_id;
                        var dateStr = $.fullCalendar.formatDate(event.start, 'yyyy-MM-dd');

                        // Simpan data event untuk mode edit
                        currentEventData = {
                            dateStr: dateStr,
                            jam: event.jam || '',
                            tempat: event.tempat_kunjungan || '',
                            agenda: event.agenda_text || event.title || '',
                            peserta: event.peserta || '',
                            dokumen: event.dokumen || '',
                            created_by: event.created_by || ''
                        };

                        // Isi detail view
                        $('#editAgendaId').val(currentAgendaId);
                        $('#detailTanggal').text(formatTanggal(dateStr));
                        if (currentEventData.jam) {
                            $('#detailJam').text(currentEventData.jam);
                            $('#detailJamRow').show();
                        } else {
                            $('#detailJamRow').hide();
                        }
                        $('#detailTempat').text(currentEventData.tempat || '-');
                        $('#detailAgenda').text(currentEventData.agenda || '-');
                        $('#detailPeserta').text(currentEventData.peserta || '-');
                        $('#detailCreatedBy').text(currentEventData.created_by || '-');
                        
                        // Dokumen
                        if (currentEventData.dokumen) {
                            $('#detailDokumenRow').show();
                            $('#detailDokumen').html('<a href="uploads/agenda/' + currentEventData.dokumen + '" target="_blank"><i class="fa fa-download"></i> ' + currentEventData.dokumen + '</a>');
                        } else {
                            $('#detailDokumenRow').hide();
                        }

                        // Selalu buka dalam mode lihat
                        showViewMode();
                        $('#modalDetailAgenda').modal('show');
                    }
                },
                
                eventRender: function(event, element) {
                    // Untuk agenda internal, tampilkan ringkas di kotak tanggal
                    if (event.id && event.id.toString().indexOf('agenda-') === 0) {
                        element.addClass('agenda-event');
                        element.removeAttr('title');
                        
                        // Bangun HTML compact: jam + judul, tempat & peserta
                        var titleText = event.agenda_text || event.title;
                        if (event.jam) {
                            titleText = event.jam + ' - ' + titleText;
                        }
                        var html = '<span class="evt-title">' + titleText + '</span>';
                        
                        // Tambah detail tempat dan peserta
                        var details = '';
                        if (event.tempat_kunjungan) {
                            details += '<i class="fa fa-map-marker"></i> ' + event.tempat_kunjungan;
                        }
                        if (event.tempat_kunjungan && event.peserta) {
                            details += '<br>';
                        }
                        if (event.peserta) {
                            details += '<i class="fa fa-users"></i> ' + event.peserta;
                        }
                        if (details) {
                            html += '<span class="evt-detail">' + details + '</span>';
                        }
                        
                        // Ganti isi title di dalam event element
                        element.find('.fc-event-title').html(html);
                    } else {
                        // Event libur/cuti bersama — gunakan tooltip bawaan
                        if(event.description) {
                            element.attr('title', event.description);
                        }
                    }
                }
            });
        } catch (e) {
            console.error("Gagal menginisialisasi kalender:", e);
            $('#calendar').html('<div class="alert alert-danger">Gagal memuat kalender. Pastikan Javascript diaktifkan. Error: ' + e.message + '</div>');
        }
        
        // Submit form tambah agenda
        $('#formAgenda').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var btnSimpan = $('#btnSimpanAgenda');
            btnSimpan.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
            
            $.ajax({
                url: 'index.php?par=47',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status == 'success') {
                        $('#modalTambahAgenda').modal('hide');
                        alert('Agenda berhasil disimpan!');
                        location.reload();
                    } else {
                        alert('Gagal: ' + res.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                },
                complete: function() {
                    btnSimpan.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan');
                }
            });
        });

        // Klik tombol Edit → switch ke mode edit
        $('#btnEditAgenda').on('click', function() {
            showEditMode();
        });

        // Klik tombol Batal Edit → kembali ke mode lihat
        $('#btnBatalEdit').on('click', function() {
            showViewMode();
        });

        // Reset ke mode lihat saat modal ditutup
        $('#modalDetailAgenda').on('hidden.bs.modal', function() {
            showViewMode();
        });

        // Submit form edit agenda
        $('#formEditAgenda').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var btnSimpan = $('#btnSimpanEdit');
            btnSimpan.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menyimpan...');
            
            $.ajax({
                url: 'index.php?par=47',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status == 'success') {
                        $('#modalDetailAgenda').modal('hide');
                        alert('Agenda berhasil diperbarui!');
                        location.reload();
                    } else {
                        alert('Gagal: ' + res.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                },
                complete: function() {
                    btnSimpan.prop('disabled', false).html('<i class="fa fa-save"></i> Simpan Perubahan');
                }
            });
        });
        
        // Hapus agenda
        $('#btnHapusAgenda').on('click', function() {
            if (!currentAgendaId) return;
            if (!confirm('Yakin ingin menghapus agenda ini?')) return;
            
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Menghapus...');
            
            $.ajax({
                url: 'index.php?par=47',
                type: 'POST',
                data: { action: 'delete', id: currentAgendaId },
                dataType: 'json',
                success: function(res) {
                    if (res.status == 'success') {
                        $('#modalDetailAgenda').modal('hide');
                        alert('Agenda berhasil dihapus!');
                        location.reload();
                    } else {
                        alert('Gagal: ' + res.message);
                    }
                },
                error: function() {
                    alert('Terjadi kesalahan. Silakan coba lagi.');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fa fa-trash"></i> Hapus');
                }
            });
        });
    });
</script>

