<?php
// 
session_start();
include "dbase.php";
include "islogin.php";

// Helper function untuk menghitung otomatis cicilan yg aman (tdk melebihi sisa)
// Deduction hanya untuk pinjaman AKTIF (Belum Lunas), dimulai dari periode_awal.
// Riwayat potongan bulan2 yg sudah benar dijaga oleh lock sync_pinjaman=1 di tgaji.
function get_pot_pinjaman_auto($conn, $iduser, $periode = null) {
    $pot = 0;
    try {
        $sql = "
            SELECT cicilan_perbulan, sisa_pinjaman
            FROM tpinjaman 
            WHERE iduser_pemohon = :iduser 
              AND status_approval = 'Approved' 
              AND (status_lunas = 'Belum' OR status_lunas = '' OR status_lunas IS NULL)
        ";
        $params = [':iduser' => $iduser];

        if ($periode) {
            // Potongan mulai dari periode_awal (bukan tgl_pengajuan)
            $sql .= " AND (periode_awal IS NULL OR periode_awal <= :periode)";
            $params[':periode'] = $periode;
        }

        $qp = $conn->prepare($sql);
        $qp->execute($params);
        while ($r = $qp->fetch(PDO::FETCH_ASSOC)) {
            $cicilan = floatval($r['cicilan_perbulan']);
            $sisa    = floatval($r['sisa_pinjaman']);
            $bayar   = ($cicilan > 0 && $cicilan < $sisa) ? $cicilan : $sisa;
            if ($bayar <= 0 && $sisa > 0) $bayar = $sisa;
            $pot += $bayar;
        }
    } catch(Exception $e) {}
    return $pot;
}

// AJAX Handler: Ambil data gaji berdasarkan ID
if (isset($_GET['ajax'])) {
    if ($_GET['ajax'] == 'get_gaji_data') {
        $id = $_GET['id'] ?? 0;
        try {
            $q = $conn->prepare("
                SELECT g.*, ru.nama AS nama_pegawai 
                FROM tgaji g 
                LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser 
                WHERE g.id = :id
            ");
            $q->execute([':id' => $id]);
            $data = $q->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                // Tambahan info untuk info_cicilan_aktif & info_lembur_aktif di modal
                $data['cicilan_pinjaman_aktif'] = get_pot_pinjaman_auto($conn, $data['iduser_pegawai'], $data['periode']);
                
                $qlembur = $conn->prepare("
                    SELECT COUNT(*) AS jumlah_lembur
                    FROM tdtllembur dl
                    JOIN tlembur l ON dl.idlembur = l.id
                    WHERE dl.iduser_pegawai = :iduser 
                      AND l.status_approval = 'Approved'
                      AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
                ");
                $qlembur->execute([':iduser' => $data['iduser_pegawai'], ':periode' => $data['periode']]);
                $rlembur = $qlembur->fetch(PDO::FETCH_ASSOC);
                $data['jumlah_lembur'] = intval($rlembur['jumlah_lembur']);
                $data['lembur_auto'] = $data['jumlah_lembur'] * 90000;

                echo json_encode($data);
            } else {
                echo json_encode(['error' => 'Data tidak ditemukan']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_GET['ajax'] == 'hitung_thr') {
        $idpegawai = $_GET['idpegawai'] ?? '';
        $periode = $_GET['periode'] ?? date('Y-m');
        $gp = floatval($_GET['gaji_pokok'] ?? 0);
        $tj = floatval($_GET['tunj_jabatan'] ?? 0);
        $tahun_thr = substr($periode, 0, 4);

        try {
            $q = $conn->prepare("SELECT tgl_masuk FROM ruser WHERE iduser = :id");
            $q->execute([':id' => $idpegawai]);
            $user = $q->fetch(PDO::FETCH_ASSOC);

            if ($user && !empty($user['tgl_masuk'])) {
                $tgl_masuk = new DateTime($user['tgl_masuk']);
                $tgl_akhir = new DateTime($tahun_thr . '-12-31');
                
                $diff = $tgl_masuk->diff($tgl_akhir);
                $total_bulan = ($diff->y * 12) + $diff->m;
                
                $satuan_upah = $gp + $tj;
                if ($total_bulan < 1) {
                    $thr = 0;
                } else if ($total_bulan >= 12) {
                    $thr = $satuan_upah;
                } else {
                    $thr = ($total_bulan / 12) * $satuan_upah;
                }

                echo json_encode([
                    'masa_kerja_bulan' => $total_bulan,
                    'satuan_upah' => $satuan_upah,
                    'thr' => round($thr)
                ]);
            } else {
                echo json_encode(['error' => 'Data pegawai/tgl masuk tidak ditemukan']);
            }
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}

$pesan = "";
$tglini = date('Y-m-d');
$hariini = date('d');

// ========== AUTO-MIGRATION: Add missing columns ==========
try {
    $cek = $conn->query("SHOW COLUMNS FROM tgaji LIKE 'bukti_tf'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tgaji ADD COLUMN bukti_tf VARCHAR(255) DEFAULT NULL");
    }
} catch (Exception $e) {}

try {
    $cek = $conn->query("SHOW COLUMNS FROM tgaji LIKE 'sync_pinjaman'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tgaji ADD COLUMN sync_pinjaman TINYINT(1) DEFAULT 0 AFTER pot_lain");
    }
} catch (Exception $e) {}

// No THR column auto-migration in regular payroll


try {
    $cek = $conn->query("SHOW COLUMNS FROM tgaji LIKE 'keterangan'");
    if ($cek->rowCount() == 0) {
        $conn->exec("ALTER TABLE tgaji ADD COLUMN keterangan TEXT DEFAULT NULL");
    }
} catch (Exception $e) {}

// Create upload directory if not exists
if (!is_dir(__DIR__ . '/uploads/bukti_tf')) {
    @mkdir(__DIR__ . '/uploads/bukti_tf', 0755, true);
}

// Validasi session user login
if (!isset($_SESSION['DEFAULT_IDUSER']) || empty($_SESSION['DEFAULT_IDUSER'])) {
    die('ERROR: SESSION USER TIDAK VALID');
}

$iduser = $_SESSION['DEFAULT_IDUSER'];

// Cek apakah mode edit
$is_edit_mode = false;
$edit_data = null;
$id_edit = isset($_GET['edit']) ? trim($_GET['edit']) : '';

if (!empty($id_edit)) {
    $is_edit_mode = true;
    // Get data untuk edit
    $qedit = $conn->prepare("SELECT * FROM tgaji WHERE id = :id");
    $qedit->bindParam(':id', $id_edit);
    $qedit->execute();
    $edit_data = $qedit->fetch();
}

// Proses Add Pegawai ke Gaji
if (isset($_POST['add_pegawai'])) {
    $idpegawai = $_POST['idpegawai'] ?? '';
    $periode = $_POST['periode'] ?? '';
    $gaji_pokok = str_replace('.', '', $_POST['gaji_pokok'] ?? '0');
    $tunj_jabatan = str_replace('.', '', $_POST['tunj_jabatan'] ?? '0');
    $tunj_perjalanan = str_replace('.', '', $_POST['tunj_perjalanan'] ?? '0');

    
    if (!$idpegawai || !$periode) {
        $pesan = "Pegawai dan periode wajib diisi!";
    } else {
        try {
            // Cek apakah pegawai sudah ada di periode tersebut
            $qcek = $conn->prepare("SELECT id FROM tgaji WHERE iduser_pegawai = :idpegawai AND periode = :periode");
            $qcek->execute([':idpegawai' => $idpegawai, ':periode' => $periode]);
            
            if ($qcek->fetch()) {
                $pesan = "Pegawai sudah ada di periode tersebut!";
            } else {
                // Auto-fill potongan pinjaman dari tpinjaman (Approved + Belum Lunas)
                $pot_pinjaman_auto = get_pot_pinjaman_auto($conn, $idpegawai, $periode);
                
                // Auto-fill lembur from approved overtime (Rp 90.000/lembur)
                $qlembur = $conn->prepare("
                    SELECT COUNT(*) AS jumlah_lembur
                    FROM tdtllembur dl
                    JOIN tlembur l ON dl.idlembur = l.id
                    WHERE dl.iduser_pegawai = :iduser 
                      AND l.status_approval = 'Approved'
                      AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
                ");
                $qlembur->execute([':iduser' => $idpegawai, ':periode' => $periode]);
                $rlembur = $qlembur->fetch(PDO::FETCH_ASSOC);
                $jumlah_lembur = intval($rlembur['jumlah_lembur']);
                $lembur_auto = $jumlah_lembur * 90000;
                
                // Hitung total terima (pendapatan - potongan pinjaman)
                // Hitung total terima (pendapatan - potongan pinjaman)
                $total_pendapatan = $gaji_pokok + $tunj_jabatan + $tunj_perjalanan + $lembur_auto;
                $total_terima = $total_pendapatan - $pot_pinjaman_auto;

                
                $stmt = $conn->prepare("
                    INSERT INTO tgaji
                    (iduser_pegawai, periode, gaji_pokok, tunj_jabatan, tunj_perjalanan, lembur, bonus,
                     bpjs_tk, bpjs_kesehatan, pot_pinjaman, pot_lain, total_terima, status_gaji, tgl_input)
                    VALUES
                    (:idpegawai, :periode, :gaji_pokok, :tunj_jabatan, :tunj_perjalanan, :lembur_auto, 0, 
                     0, 0, :pot_pinjaman, 0, :total_terima, 'Draft', NOW())
                ");

                
                $stmt->execute([
                    ':idpegawai' => $idpegawai,
                    ':periode' => $periode,
                    ':gaji_pokok' => $gaji_pokok,
                    ':tunj_jabatan' => $tunj_jabatan,
                    ':tunj_perjalanan' => $tunj_perjalanan,
                    ':lembur_auto' => $lembur_auto,
                    ':pot_pinjaman' => $pot_pinjaman_auto,
                    ':total_terima' => $total_terima
                ]);

                
                $pesanPinjaman = $pot_pinjaman_auto > 0 ? " (Potongan pinjaman otomatis: Rp " . number_format($pot_pinjaman_auto, 0, ',', '.') . ")" : "";
                $pesanLembur = $lembur_auto > 0 ? " (Lembur otomatis: $jumlah_lembur x Rp 90.000 = Rp " . number_format($lembur_auto, 0, ',', '.') . ")" : "";
                $pesan = "Pegawai berhasil ditambahkan ke daftar gaji!" . $pesanPinjaman . $pesanLembur;
            }
        } catch (Exception $e) {
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}

// Proses Adjust Gaji
if (isset($_POST['adjust_gaji'])) {
    $id_gaji = $_POST['id_gaji'] ?? '';
    $gaji_pokok = str_replace('.', '', $_POST['gaji_pokok'] ?? '0');
    $tunj_jabatan = str_replace('.', '', $_POST['tunj_jabatan'] ?? '0');
    $tunj_perjalanan = str_replace('.', '', $_POST['tunj_perjalanan'] ?? '0');
    $lembur = str_replace('.', '', $_POST['lembur'] ?? '0');
    $bonus = str_replace('.', '', $_POST['bonus'] ?? '0');

    $bpjs_tk = str_replace('.', '', $_POST['bpjs_tk'] ?? '0');
    $bpjs_kesehatan = str_replace('.', '', $_POST['bpjs_kesehatan'] ?? '0');
    $pot_pinjaman = str_replace('.', '', $_POST['pot_pinjaman'] ?? '0');
    $pot_lain = str_replace('.', '', $_POST['pot_lain'] ?? '0');
    $keterangan_gaji = trim($_POST['keterangan_gaji'] ?? '');
    
    if (!$id_gaji) {
        $pesan = "ID Gaji tidak valid!";
    } else {
        try {
            // Hitung total pendapatan
            $total_pendapatan = $gaji_pokok + $tunj_jabatan + $tunj_perjalanan + $lembur + $bonus;

            
            // Hitung total potongan
            $total_potongan = $bpjs_tk + $bpjs_kesehatan + $pot_pinjaman + $pot_lain;
            
            // Hitung total diterima
            $total_terima = $total_pendapatan - $total_potongan;
            
            // Handle multi bukti TF upload
            $bukti_tf_sql = '';
            $bukti_tf_param = [];
            
            // Get existing bukti files
            $qExBukti = $conn->prepare("SELECT bukti_tf FROM tgaji WHERE id = :id");
            $qExBukti->execute([':id' => $id_gaji]);
            $rowExBukti = $qExBukti->fetch(PDO::FETCH_ASSOC);
            $existingFiles = [];
            if (!empty($rowExBukti['bukti_tf'])) {
                $decoded = json_decode($rowExBukti['bukti_tf'], true);
                $existingFiles = is_array($decoded) ? $decoded : [$rowExBukti['bukti_tf']];
            }
            
            $newFiles = [];
            if (isset($_FILES['bukti_tf']) && is_array($_FILES['bukti_tf']['name'])) {
                $fileCount = count($_FILES['bukti_tf']['name']);
                for ($f = 0; $f < $fileCount; $f++) {
                    if ($_FILES['bukti_tf']['error'][$f] == UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['bukti_tf']['name'][$f], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
                        if (in_array($ext, $allowed)) {
                            $newName = 'bukti_tf_' . $id_gaji . '_' . time() . '_' . $f . '.' . $ext;
                            $dest = __DIR__ . '/uploads/bukti_tf/' . $newName;
                            if (move_uploaded_file($_FILES['bukti_tf']['tmp_name'][$f], $dest)) {
                                $newFiles[] = $newName;
                            }
                        }
                    }
                }
            }
            
            if (!empty($newFiles)) {
                $allFiles = array_merge($existingFiles, $newFiles);
                $bukti_tf_sql = ', bukti_tf = :bukti_tf';
                $bukti_tf_param[':bukti_tf'] = json_encode($allFiles);
            }
            
            $stmt = $conn->prepare("
                UPDATE tgaji SET
                    gaji_pokok = :gaji_pokok,
                    tunj_jabatan = :tunj_jabatan,
                    tunj_perjalanan = :tunj_perjalanan,
                    lembur = :lembur,
                    bonus = :bonus,

                    bpjs_tk = :bpjs_tk,
                    bpjs_kesehatan = :bpjs_kesehatan,
                    pot_pinjaman = :pot_pinjaman,
                    pot_lain = :pot_lain,
                    total_terima = :total_terima,
                    keterangan = :keterangan_gaji,
                    tgl_update = NOW()
                    $bukti_tf_sql
                WHERE id = :id
            ");
            
            $executeParams = [
                ':gaji_pokok' => $gaji_pokok,
                ':tunj_jabatan' => $tunj_jabatan,
                ':tunj_perjalanan' => $tunj_perjalanan,
                ':lembur' => $lembur,
                ':bonus' => $bonus,

                ':bpjs_tk' => $bpjs_tk,
                ':bpjs_kesehatan' => $bpjs_kesehatan,
                ':pot_pinjaman' => $pot_pinjaman,
                ':pot_lain' => $pot_lain,
                ':total_terima' => $total_terima,
                ':keterangan_gaji' => $keterangan_gaji,
                ':id' => $id_gaji
            ];
            $executeParams = array_merge($executeParams, $bukti_tf_param);
            
            $stmt->execute($executeParams);
            
            $pesan = "Data gaji berhasil diupdate!";
        } catch (Exception $e) {
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}

// Proses Generate Gaji
if (isset($_POST['generate_gaji'])) {
    $periode = $_POST['periode_generate'] ?? '';
    
    if (!$periode) {
        $pesan = "Periode wajib diisi!";
    } else {
        try {
            $conn->beginTransaction();
            
            // STEP 1: Ambil semua pegawai yang punya data gaji sebelumnya tapi BELUM ada di periode ini
            $qRef = $conn->prepare("
                SELECT g.iduser_pegawai, g.gaji_pokok, g.tunj_jabatan, g.tunj_perjalanan,
                       g.bpjs_tk, g.bpjs_kesehatan
                FROM tgaji g
                INNER JOIN (
                    SELECT iduser_pegawai, MAX(periode) AS last_periode
                    FROM tgaji
                    GROUP BY iduser_pegawai
                ) latest ON g.iduser_pegawai = latest.iduser_pegawai 
                        AND g.periode = latest.last_periode
                WHERE g.iduser_pegawai NOT IN (
                    SELECT iduser_pegawai FROM tgaji WHERE periode = :periode
                )
            ");
            $qRef->execute([':periode' => $periode]);
            $refData = $qRef->fetchAll(PDO::FETCH_ASSOC);
            
            $jumlahBaru = 0;
            
            foreach ($refData as $ref) {
                // Hitung lembur approved untuk pegawai ini di periode baru
                $lembur_auto = 0;
                try {
                    $qlembur = $conn->prepare("
                        SELECT COUNT(*) AS jumlah_lembur
                        FROM tdtllembur dl
                        JOIN tlembur l ON dl.idlembur = l.id
                        WHERE dl.iduser_pegawai = :iduser 
                          AND l.status_approval = 'Approved'
                          AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
                    ");
                    $qlembur->execute([':iduser' => $ref['iduser_pegawai'], ':periode' => $periode]);
                    $rlembur = $qlembur->fetch(PDO::FETCH_ASSOC);
                    $lembur_auto = intval($rlembur['jumlah_lembur']) * 90000;
                } catch (Exception $e) {}
                
                // Hitung potongan pinjaman aktif
                $pot_pinjaman = get_pot_pinjaman_auto($conn, $ref['iduser_pegawai'], $periode);
                
                // Hitung total
                $total_pendapatan = floatval($ref['gaji_pokok']) + floatval($ref['tunj_jabatan']) 
                                  + floatval($ref['tunj_perjalanan']) + $lembur_auto; 
                // THR tidak digenerate otomatis ke periode baru, manual adjust.
                $total_potongan = floatval($ref['bpjs_tk']) + floatval($ref['bpjs_kesehatan']) + $pot_pinjaman;
                $total_terima = $total_pendapatan - $total_potongan;
                
                // Insert data gaji baru
                $stmtIns = $conn->prepare("
                    INSERT INTO tgaji
                    (iduser_pegawai, periode, gaji_pokok, tunj_jabatan, tunj_perjalanan, lembur, bonus, thr,
                     bpjs_tk, bpjs_kesehatan, pot_pinjaman, pot_lain, total_terima, status_gaji, tgl_input, tgl_generate)
                    VALUES
                    (:idpegawai, :periode, :gaji_pokok, :tunj_jabatan, :tunj_perjalanan, :lembur, 0, 0,
                     :bpjs_tk, :bpjs_kesehatan, :pot_pinjaman, 0, :total_terima, 'Generated', NOW(), NOW())
                ");
                
                $stmtIns->execute([
                    ':idpegawai' => $ref['iduser_pegawai'],
                    ':periode' => $periode,
                    ':gaji_pokok' => $ref['gaji_pokok'],
                    ':tunj_jabatan' => $ref['tunj_jabatan'],
                    ':tunj_perjalanan' => $ref['tunj_perjalanan'],
                    ':lembur' => $lembur_auto,
                    ':bpjs_tk' => $ref['bpjs_tk'],
                    ':bpjs_kesehatan' => $ref['bpjs_kesehatan'],
                    ':pot_pinjaman' => $pot_pinjaman,
                    ':total_terima' => $total_terima
                ]);
                
                $jumlahBaru++;
            }
            
            // STEP 2: Update Draft yang sudah ada → Generated
            $stmtUpd = $conn->prepare("
                UPDATE tgaji SET
                    status_gaji = 'Generated',
                    tgl_generate = NOW()
                WHERE periode = :periode AND status_gaji = 'Draft'
            ");
            $stmtUpd->execute([':periode' => $periode]);
            $jumlahUpdate = $stmtUpd->rowCount();
            
            // STEP 3: Update potongan pinjaman & lembur untuk pegawai yang SUDAH ADA di periode ini
            // CATATAN: record dengan sync_pinjaman = 1 sudah terkunci — tidak boleh diubah pot_pinjaman-nya
            $qExisting = $conn->prepare("
                SELECT g.id, g.iduser_pegawai, g.gaji_pokok, g.tunj_jabatan, g.tunj_perjalanan,
                       g.bonus, g.thr, g.bpjs_tk, g.bpjs_kesehatan, g.pot_lain, g.sync_pinjaman
                FROM tgaji g
                WHERE g.periode = :periode
            ");
            $qExisting->execute([':periode' => $periode]);
            $existingData = $qExisting->fetchAll(PDO::FETCH_ASSOC);
            
            $jumlahSinkron = 0;
            
            foreach ($existingData as $ex) {
                // Jika sudah pernah di-sync (terkunci), jangan ubah pot_pinjaman
                if (intval($ex['sync_pinjaman']) === 1) {
                    // Hanya update lembur & total_terima, pot_pinjaman tetap
                    $lembur_locked = 0;
                    try {
                        $qlmb = $conn->prepare("
                            SELECT COUNT(*) AS jumlah_lembur
                            FROM tdtllembur dl
                            JOIN tlembur l ON dl.idlembur = l.id
                            WHERE dl.iduser_pegawai = :iduser 
                              AND l.status_approval = 'Approved'
                              AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
                        ");
                        $qlmb->execute([':iduser' => $ex['iduser_pegawai'], ':periode' => $periode]);
                        $rlmb = $qlmb->fetch(PDO::FETCH_ASSOC);
                        $lembur_locked = intval($rlmb['jumlah_lembur']) * 90000;
                    } catch (Exception $e) {}

                    // Ambil pot_pinjaman yang sudah tersimpan (tidak diubah)
                    $qCurPot = $conn->prepare("SELECT pot_pinjaman FROM tgaji WHERE id = :id");
                    $qCurPot->execute([':id' => $ex['id']]);
                    $curPot = floatval($qCurPot->fetchColumn());

                    $total_pend_locked = floatval($ex['gaji_pokok']) + floatval($ex['tunj_jabatan'])
                                       + floatval($ex['tunj_perjalanan']) + $lembur_locked + floatval($ex['bonus']);

                    $total_pot_locked  = floatval($ex['bpjs_tk']) + floatval($ex['bpjs_kesehatan'])
                                       + $curPot + floatval($ex['pot_lain']);
                    $total_terima_locked = $total_pend_locked - $total_pot_locked;

                    $conn->prepare("
                        UPDATE tgaji SET lembur = :lembur, total_terima = :total_terima, tgl_generate = NOW()
                        WHERE id = :id
                    ")->execute([':lembur' => $lembur_locked, ':total_terima' => $total_terima_locked, ':id' => $ex['id']]);
                    continue;
                }

                // Record belum terkunci: hitung ulang pot_pinjaman
                $pot_pinjaman_new = get_pot_pinjaman_auto($conn, $ex['iduser_pegawai'], $periode);
                
                // Hitung lembur approved terbaru
                $lembur_new = 0;
                try {
                    $qlmb = $conn->prepare("
                        SELECT COUNT(*) AS jumlah_lembur
                        FROM tdtllembur dl
                        JOIN tlembur l ON dl.idlembur = l.id
                        WHERE dl.iduser_pegawai = :iduser 
                          AND l.status_approval = 'Approved'
                          AND DATE_FORMAT(l.tgl_lembur, '%Y-%m') = :periode
                    ");
                    $qlmb->execute([':iduser' => $ex['iduser_pegawai'], ':periode' => $periode]);
                    $rlmb = $qlmb->fetch(PDO::FETCH_ASSOC);
                    $lembur_new = intval($rlmb['jumlah_lembur']) * 90000;
                } catch (Exception $e) {}
                
                // Hitung total terima baru
                $total_pendapatan_new = floatval($ex['gaji_pokok']) + floatval($ex['tunj_jabatan']) 
                                      + floatval($ex['tunj_perjalanan']) + $lembur_new + floatval($ex['bonus']);

                $total_potongan_new = floatval($ex['bpjs_tk']) + floatval($ex['bpjs_kesehatan']) 
                                    + $pot_pinjaman_new + floatval($ex['pot_lain']);
                $total_terima_new = $total_pendapatan_new - $total_potongan_new;
                
                // Update data gaji (lock dilakukan oleh STEP 4 setelah sync ke tpinjaman)
                $stmtSync = $conn->prepare("
                    UPDATE tgaji SET
                        pot_pinjaman = :pot_pinjaman,
                        lembur = :lembur,
                        total_terima = :total_terima,
                        tgl_generate = NOW()
                    WHERE id = :id
                ");
                $stmtSync->execute([
                    ':pot_pinjaman' => $pot_pinjaman_new,
                    ':lembur' => $lembur_new,
                    ':total_terima' => $total_terima_new,
                    ':id' => $ex['id']
                ]);
                
                if ($stmtSync->rowCount() > 0) {
                    $jumlahSinkron++;
                }
            }
            
            // STEP 4: Sinkronisasi pemotongan ke tpinjaman 
            $qSync = $conn->prepare("
                SELECT id, iduser_pegawai, pot_pinjaman 
                FROM tgaji 
                WHERE periode = :periode AND status_gaji = 'Generated' AND sync_pinjaman = 0 AND pot_pinjaman > 0
            ");
            $qSync->execute([':periode' => $periode]);
            $gajiToSync = $qSync->fetchAll(PDO::FETCH_ASSOC);

            $jumlahDibayarSync = 0;
            foreach ($gajiToSync as $gs) {
                $distribusi = floatval($gs['pot_pinjaman']);
                if ($distribusi > 0) {
                    $qLoans = $conn->prepare("
                        SELECT id, cicilan_perbulan, sisa_pinjaman, jumlah_dibayar 
                        FROM tpinjaman 
                        WHERE iduser_pemohon = :iduser 
                          AND status_approval = 'Approved' 
                          AND (status_lunas = 'Belum' OR status_lunas = '' OR status_lunas IS NULL)
                        ORDER BY id ASC
                    ");
                    $qLoans->execute([':iduser' => $gs['iduser_pegawai']]);
                    $loans = $qLoans->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($loans as $ln) {
                        if ($distribusi <= 0) break;
                        $sisa = floatval($ln['sisa_pinjaman']);
                        $cicilan = floatval($ln['cicilan_perbulan']);
                        $batas = ($cicilan > 0 && $cicilan < $sisa) ? $cicilan : $sisa;
                        if ($batas <= 0 && $sisa > 0) $batas = $sisa;
                        
                        $bayar = min($distribusi, $batas);
                        
                        if ($bayar > 0) {
                            $new_dibayar = floatval($ln['jumlah_dibayar']) + $bayar;
                            $new_sisa = $sisa - $bayar;
                            if ($new_sisa < 0) $new_sisa = 0;
                            $status_ln = ($new_sisa <= 0) ? 'Lunas' : 'Belum';
                            
                            $uLoan = $conn->prepare("UPDATE tpinjaman SET jumlah_dibayar = :jb, sisa_pinjaman = :sp, status_lunas = :sl WHERE id = :id");
                            $uLoan->execute([':jb' => $new_dibayar, ':sp' => $new_sisa, ':sl' => $status_ln, ':id' => $ln['id']]);
                            
                            $distribusi -= $bayar;
                            $jumlahDibayarSync++;
                        }
                    }
                }
                $conn->prepare("UPDATE tgaji SET sync_pinjaman = 1 WHERE id = :id")->execute([':id' => $gs['id']]);
            }
            
            $conn->commit();
            
            $pesanParts = [];
            if ($jumlahBaru > 0) $pesanParts[] = "$jumlahBaru pegawai otomatis dari data terakhir";
            if ($jumlahUpdate > 0) $pesanParts[] = "$jumlahUpdate pegawai Draft → Generated";
            if ($jumlahSinkron > 0) $pesanParts[] = "$jumlahSinkron pegawai potongan pinjaman di-sinkronkan";
            if ($jumlahDibayarSync > 0) $pesanParts[] = "$jumlahDibayarSync tagihan pinjaman telah dilunasi potongannya";
            
            $pesanDetail = implode(', ', $pesanParts);
            if (empty($pesanDetail)) $pesanDetail = "Semua pegawai sudah ada di periode ini, tidak ada sinkronisasi baru";
            
            $pesan = "Berhasil generate gaji periode $periode! ($pesanDetail)";
            
        } catch (Exception $e) {
            $conn->rollBack();
            $pesan = "ERROR: " . $e->getMessage();
        }
    }
}

// Proses Impor Excel
if (isset($_POST['proses_import'])) {
    if (isset($_FILES['file_excel']) && $_FILES['file_excel']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['file_excel']['tmp_name'];
        
        try {
            require 'vendor/autoload.php';
            
            // Pengaman: Jika ZipArchive tidak ada, jangan gunakan autodetect (IOFactory::load) 
            // karena ia akan mencoba memanggil reader Xlsx yang memicu fatal error.
            if (!class_exists('ZipArchive')) {
                // Jika Zip tidak ada, hanya bisa baca XLS
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xls');
            } else {
                try {
                    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($file_tmp);
                } catch (Exception $e) {
                    throw new Exception("Format file tidak dikenali. Pastikan file adalah Excel (.xls atau .xlsx).");
                }
            }
            
            try {
                $spreadsheet = $reader->load($file_tmp);
            } catch (Exception $e) {
                if (!class_exists('ZipArchive')) {
                    throw new Exception("Server Anda tidak mendukung file .xlsx. Silakan unduh ulang template (format .xls) dan gunakan itu.");
                } else {
                    throw new Exception("Gagal membaca isi file: " . $e->getMessage());
                }
            }
            
            $datas = $spreadsheet->getActiveSheet()->toArray();
            
            $succ = 0;
            $err = 0;
            $errors = [];
            
            // Mulai dari baris ke-2 (index 1) karena baris 1 adalah header
            for ($i = 1; $i < count($datas); $i++) {
                $idpegawai = trim($datas[$i][0] ?? '');
                // Index 1 sekarang adalah Nama Pegawai (Info), kita loncati
                $periode = trim($datas[$i][2] ?? '');
                
                if (empty($idpegawai) || empty($periode)) continue;
                
                // UNIVERSAL FINANCE PARSER (V4) - Anti Bug Nol Hilang
                $parseNum = function($raw) {
                    $clean = str_replace(['Rp', ' ', 'rp'], '', trim($raw ?? '0'));
                    $has_dot = (strpos($clean, '.') !== false);
                    $has_comma = (strpos($clean, ',') !== false);
                    if ($has_dot && $has_comma) {
                        $dot_p = strrpos($clean, '.'); $comma_p = strrpos($clean, ',');
                        if ($dot_p > $comma_p) { $clean = str_replace(',', '', $clean); }
                        else { $clean = str_replace('.', '', $clean); $clean = str_replace(',', '.', $clean); }
                    } elseif ($has_dot) {
                        if (substr_count($clean, '.') > 1 || preg_match('/\.\d{3}$/', $clean)) { $clean = str_replace('.', '', $clean); }
                    } elseif ($has_comma) {
                        if (substr_count($clean, ',') > 1 || preg_match('/,\d{3}$/', $clean)) { $clean = str_replace(',', '', $clean); }
                        else { $clean = str_replace(',', '.', $clean); }
                    }
                    return floatval($clean);
                };

                $gp = $parseNum($datas[$i][3]);
                $tj = $parseNum($datas[$i][4]);
                $tp = $parseNum($datas[$i][5]);
                $lm = $parseNum($datas[$i][6]);
                $bo = $parseNum($datas[$i][7]);
                $bt = $parseNum($datas[$i][8]);
                $bk = $parseNum($datas[$i][9]);
                $pp = $parseNum($datas[$i][10]);
                $pl = $parseNum($datas[$i][11]);
                $ket = trim($datas[$i][12] ?? '');
                
                // Hitung total terima
                $total_pendapatan = $gp + $tj + $tp + $lm + $bo;
                $total_potongan = $bt + $bk + $pp + $pl;
                $total_terima = $total_pendapatan - $total_potongan;
                
                // Cek apakah data sudah ada?
                $qcek = $conn->prepare("SELECT id FROM tgaji WHERE iduser_pegawai = :idp AND periode = :per");
                $qcek->execute([':idp' => $idpegawai, ':per' => $periode]);
                $existing = $qcek->fetch();
                
                if ($existing) {
                    // Jika sudah ada, lakukan UPDATE (Timpa data lama)
                    $stmt = $conn->prepare("
                        UPDATE tgaji SET 
                            gaji_pokok = :gp, tunj_jabatan = :tj, tunj_perjalanan = :tp, 
                            lembur = :lm, bonus = :bo, bpjs_tk = :bt, bpjs_kesehatan = :bk, 
                            pot_pinjaman = :pp, pot_lain = :pl, total_terima = :tt, 
                            keterangan = :ket, tgl_input = NOW(), status_gaji = 'Imported'
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':id' => $existing['id'], ':gp' => $gp, ':tj' => $tj, ':tp' => $tp,
                        ':lm' => $lm, ':bo' => $bo, ':bt' => $bt, ':bk' => $bk, ':pp' => $pp, ':pl' => $pl,
                        ':tt' => $total_terima, ':ket' => $ket
                    ]);
                } else {
                    // Jika belum ada, lakukan INSERT (Tambah baru)
                    $stmt = $conn->prepare("
                        INSERT INTO tgaji (
                            iduser_pegawai, periode, gaji_pokok, tunj_jabatan, tunj_perjalanan, 
                            lembur, bonus, bpjs_tk, bpjs_kesehatan, pot_pinjaman, pot_lain, 
                            total_terima, keterangan, status_gaji, tgl_input
                        ) VALUES (
                            :idp, :per, :gp, :tj, :tp, :lm, :bo, :bt, :bk, :pp, :pl, :tt, :ket, 'Imported', NOW()
                        )
                    ");
                    
                    $stmt->execute([
                        ':idp' => $idpegawai, ':per' => $periode, ':gp' => $gp, ':tj' => $tj, ':tp' => $tp,
                        ':lm' => $lm, ':bo' => $bo, ':bt' => $bt, ':bk' => $bk, ':pp' => $pp, ':pl' => $pl,
                        ':tt' => $total_terima, ':ket' => $ket
                    ]);
                }
                
                $succ++;
            }
            
            $pesan = "Proses Selesai! Berhasil memproses $succ data pegawai.";
            
        } catch (Exception $e) {
            $pesan = "Gagal memproses file: " . $e->getMessage();
        }
    } else {
        $pesan = "Silakan pilih file yang valid.";
    }
}

// Proses hapus data
if (isset($_POST['hapus']) && isset($_POST['id_hapus'])) {
    try {
        $id_hapus = $_POST['id_hapus'];
        
        $sql_del = "DELETE FROM tgaji WHERE id = :id";
        $stmt_del = $conn->prepare($sql_del);
        $stmt_del->bindParam(':id', $id_hapus);
        $stmt_del->execute();
        
        $pesan = "Data gaji berhasil dihapus!";
    } catch (PDOException $e) {
        $pesan = "Error: " . $e->getMessage();
    }
}

// Cari periode: dari generate, add pegawai, edit, atau default MAX
if (isset($_POST['periode_generate']) && !empty($_POST['periode_generate'])) {
    $periode_terbaru = $_POST['periode_generate'];
} elseif (isset($_POST['add_pegawai']) && !empty($_POST['periode'])) {
    $periode_terbaru = $_POST['periode'];
} elseif (isset($_POST['edit_gaji']) && !empty($_POST['periode'])) {
    $periode_terbaru = $_POST['periode'];
} else {
    $qLatest = $conn->prepare("SELECT MAX(periode) AS periode_terbaru FROM tgaji");
    $qLatest->execute();
    $rLatest = $qLatest->fetch();
    $periode_terbaru = $rLatest['periode_terbaru'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Penggajian</title>
    <!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    
    <!-- Tambahkan referensi ke library Bootstrap Datepicker -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <style>
        .komponen-section {
            background: #f8f9fa;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .komponen-header {
            font-weight: bold;
            margin-bottom: 10px;
            color: #495057;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .total-section {
            background: #e3f2fd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border: 2px solid #2196f3;
            font-weight: bold;
            font-size: 16px;
        }
        .btn-generate {
            font-size: 16px;
            padding: 10px 30px;
            font-weight: bold;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 800px;
            border-radius: 5px;
            max-height: 80vh;
            overflow-y: auto;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
        }
        .bukti-tf-upload-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 12px;
            border: 2px dashed #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            min-height: 70px;
            background: #fafafa;
        }
        .bukti-tf-upload-area:hover {
            border-color: #2196F3;
            background: #e3f2fd;
        }
        .bukti-tf-upload-area.has-files {
            border-color: #4CAF50;
            background: #e8f5e9;
        }
        .bukti-tf-gallery-item {
            border: 1px solid #eee;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }
        .bukti-tf-gallery-item:hover {
            transform: scale(1.03);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .bukti-tf-gallery-item img {
            width: 200px;
            height: 150px;
            object-fit: cover;
        }
        .bukti-tf-gallery-pdf {
            width: 200px;
            height: 150px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: #f5f5f5;
            color: #e53935;
            text-decoration: none;
        }
        .bukti-tf-gallery-pdf i {
            font-size: 40px;
            margin-bottom: 5px;
        }
        .bukti-tf-thumb {
            display: inline-block;
            position: relative;
            margin: 4px;
            border-radius: 4px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        }
        .bukti-tf-thumb img {
            width: 60px;
            height: 45px;
            object-fit: cover;
            cursor: pointer;
        }
        .bukti-tf-thumb .pdf-icon {
            width: 60px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffebee;
            color: #e53935;
            font-size: 20px;
            cursor: pointer;
        }
    </style>
    
    <script>
        // ========== MASKING RIBUAN ==========
        function formatRibuan(angka) {
            if (!angka && angka !== 0) return '';
            var str = String(angka).replace(/[^0-9]/g, '');
            if (!str || str === '0') return str;
            // Hapus leading zeros
            str = str.replace(/^0+/, '') || '0';
            return str.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        function parseRibuan(str) {
            if (!str) return 0;
            return parseFloat(String(str).replace(/\./g, '')) || 0;
        }

        function maskRibuan(el) {
            var pos = el.selectionStart;
            var oldLen = el.value.length;
            var raw = el.value.replace(/[^0-9]/g, '');
            el.value = formatRibuan(raw);
            var newLen = el.value.length;
            var newPos = pos + (newLen - oldLen);
            if (newPos < 0) newPos = 0;
            el.setSelectionRange(newPos, newPos);
        }

        $(document).ready(function(){
            // Inisialisasi datepicker untuk periode (bulan/tahun)
            $('.periode-picker').datepicker({
                format: 'yyyy-mm',
                autoclose: true,
                minViewMode: 'months',
                todayHighlight: true
            });
            
            // Cek apakah tombol Generate Gaji aktif (tanggal 27)
            // DISABLED SEMENTARA UNTUK TESTING
            // var hariIni = <?php echo $hariini; ?>;
            // if (hariIni != 27) {
            //     $('#btn_generate_gaji').prop('disabled', true);
            //     $('#btn_generate_gaji').attr('title', 'Tombol ini hanya aktif tanggal 27');
            // }

            // Attach masking ke semua input uang
            $(document).on('keyup', '.input-ribuan', function() {
                maskRibuan(this);
            });

            // Strip dots sebelum submit form
            $('form').on('submit', function() {
                $(this).find('.input-ribuan').each(function() {
                    this.value = this.value.replace(/\./g, '');
                });
            });
        });
        
        function confirmDelete(id) {
            if(confirm('Apakah Anda yakin ingin menghapus data gaji ini?')) {
                document.getElementById('id_hapus').value = id;
                document.getElementById('form_hapus').submit();
            }
        }
        
        function openAddModal() {
            document.getElementById('modalAddPegawai').style.display = 'block';
        }
        
        function closeAddModal() {
            document.getElementById('modalAddPegawai').style.display = 'none';
        }
        
        function openAdjustModal(id) {
            // Fetch data gaji via AJAX
            $.ajax({
                url: '38_penggajian.php',
                method: 'GET',
                data: {ajax: 'get_gaji_data', id: id},
                dataType: 'json',
                success: function(data) {
                    // Populate form with formatted values
                    $('#adjust_id_gaji').val(data.id);
                    $('#adjust_nama').val(data.nama_pegawai);
                    $('#adjust_periode').val(data.periode);
                    $('#adjust_gaji_pokok').val(formatRibuan(parseInt(data.gaji_pokok) || 0));
                    $('#adjust_tunj_jabatan').val(formatRibuan(parseInt(data.tunj_jabatan) || 0));
                    $('#adjust_tunj_perjalanan').val(formatRibuan(parseInt(data.tunj_perjalanan) || 0));
                    $('#adjust_lembur').val(formatRibuan(parseInt(data.lembur) || 0));
                    $('#adjust_bonus').val(formatRibuan(parseInt(data.bonus) || 0));

                    
                    window._currentIdPegawai = data.iduser_pegawai;
                    
                    $('#adjust_bpjs_tk').val(formatRibuan(parseInt(data.bpjs_tk) || 0));
                    $('#adjust_bpjs_kesehatan').val(formatRibuan(parseInt(data.bpjs_kesehatan) || 0));
                    $('#adjust_pot_pinjaman').val(formatRibuan(parseInt(data.pot_pinjaman) || 0));
                    $('#adjust_pot_lain').val(formatRibuan(parseInt(data.pot_lain) || 0));
                    
                    // Auto-sinkron potongan pinjaman aktif
                    window._cicilanPinjamanAktif = data.cicilan_pinjaman_aktif || 0;
                    if (window._cicilanPinjamanAktif > 0) {
                        $('#adjust_pot_pinjaman').val(formatRibuan(window._cicilanPinjamanAktif));
                        $('#val_cicilan_aktif').text('Rp ' + formatRibuan(window._cicilanPinjamanAktif));
                        $('#info_cicilan_aktif').show();
                    } else {
                        $('#info_cicilan_aktif').hide();
                    }
                    
                    // Auto-sinkron lembur approved
                    window._lemburAuto = data.lembur_auto || 0;
                    window._jumlahLembur = data.jumlah_lembur || 0;
                    if (window._jumlahLembur > 0) {
                        $('#adjust_lembur').val(formatRibuan(window._lemburAuto));
                        $('#val_lembur_aktif').text(window._jumlahLembur + ' kali (Rp ' + formatRibuan(window._lemburAuto) + ')');
                        $('#info_lembur_aktif').show();
                    } else {
                        $('#info_lembur_aktif').hide();
                    }
                    
                    // Populate keterangan
                    $('#adjust_keterangan').val(data.keterangan || '');
                    
                    // Show bukti TF thumbnails if exists
                    var buktiPreview = document.getElementById('adjust_bukti_preview');
                    buktiPreview.innerHTML = '';
                    if (data.bukti_tf && data.bukti_tf.length > 0) {
                        try {
                            var files = JSON.parse(data.bukti_tf);
                            if (Array.isArray(files) && files.length > 0) {
                                var label = document.createElement('div');
                                label.innerHTML = '<small style="color:green;"><i class="fa fa-check-circle"></i> <b>' + files.length + ' file</b> bukti transfer sudah diupload (upload baru akan ditambahkan):</small>';
                                buktiPreview.appendChild(label);
                                var thumbDiv = document.createElement('div');
                                thumbDiv.style.marginTop = '6px';
                                files.forEach(function(f) {
                                    var ext = f.split('.').pop().toLowerCase();
                                    var wrapper = document.createElement('span');
                                    wrapper.className = 'bukti-tf-thumb';
                                    if (ext === 'pdf') {
                                        wrapper.innerHTML = '<span class="pdf-icon" onclick="viewBuktiTfGallery([\'' + f + '\'])"><i class="fa fa-file-pdf-o"></i></span>';
                                    } else {
                                        wrapper.innerHTML = '<img src="uploads/bukti_tf/' + f + '" onclick="viewBuktiTfGallery([\'' + f + '\'])" title="' + f + '">';
                                    }
                                    thumbDiv.appendChild(wrapper);
                                });
                                buktiPreview.appendChild(thumbDiv);
                            }
                        } catch(e) {
                            // Legacy single file
                            buktiPreview.innerHTML = '<small style="color:green;"><i class="fa fa-check-circle"></i> Bukti TF sudah diupload sebelumnya</small>';
                        }
                    }
                    // Reset file input
                    $('#adjust_bukti_tf').val('');
                    
                    // Hitung total
                    hitungTotal();
                    
                    // Show modal
                    document.getElementById('modalAdjustGaji').style.display = 'block';
                },
                error: function() {
                    alert('Gagal mengambil data gaji');
                }
            });
        }
        
        function closeAdjustModal() {
            document.getElementById('modalAdjustGaji').style.display = 'none';
        }
        
        
        function hitungTotal() {
            var gajiPokok = parseRibuan($('#adjust_gaji_pokok').val());
            var tunjJabatan = parseRibuan($('#adjust_tunj_jabatan').val());
            var tunjPerjalanan = parseRibuan($('#adjust_tunj_perjalanan').val());
            var lembur = parseRibuan($('#adjust_lembur').val());
            var bonus = parseRibuan($('#adjust_bonus').val());
            
            var bpjsTk = parseRibuan($('#adjust_bpjs_tk').val());
            var bpjsKes = parseRibuan($('#adjust_bpjs_kesehatan').val());
            var potPinjaman = parseRibuan($('#adjust_pot_pinjaman').val());
            var potLain = parseRibuan($('#adjust_pot_lain').val());
            
            var totalPendapatan = gajiPokok + tunjJabatan + tunjPerjalanan + lembur + bonus;
            var totalPotongan = bpjsTk + bpjsKes + potPinjaman + potLain;
            var totalTerima = totalPendapatan - totalPotongan;
            
            $('#total_pendapatan').text('Rp ' + formatRibuan(totalPendapatan));
            $('#total_potongan').text('Rp ' + formatRibuan(totalPotongan));
            $('#total_terima').text('Rp ' + formatRibuan(totalTerima));
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modalAdd = document.getElementById('modalAddPegawai');
            var modalAdjust = document.getElementById('modalAdjustGaji');
            if (event.target == modalAdd) {
                modalAdd.style.display = 'none';
            }
            if (event.target == modalAdjust) {
                modalAdjust.style.display = 'none';
            }
        }
    </script>
</head>
<body>
<div class="row">
    <div class="breadcrumb">
        <span class="breadcrumb-title"><i class="fa fa-home"></i> PENGGAJIAN</span>
    </div>
    
    <section class="content-body">
        <header class="content-header">
            <div class="header-actions" style="margin-bottom: 15px;">
                <button type="button" class="btn btn-primary" onclick="openAddModal()">
                    <i class="fa fa-plus"></i> Add Pegawai
                </button>
                
                <button type="button" class="btn btn-success btn-generate" id="btn_generate_gaji" onclick="showGenerateForm()">
                    <i class="fa fa-cog"></i> Generate Gaji
                </button>
                
                <button type="button" class="btn btn-info" onclick="document.getElementById('modalImport').style.display='block'">
                   <i class="fa fa-file-excel-o"></i> Kelola Gaji (Excel)
                </button>

                <?php if (!empty($periode_terbaru)): ?>
                <button type="button" class="btn btn-default" onclick="cetakAllZip('<?= htmlspecialchars($periode_terbaru) ?>')" style="font-size:14px; padding:8px 20px;">
                    <i class="fa fa-file-archive-o"></i> Cetak All (ZIP)
                </button>
                <?php endif; ?>
            </div>
            
            <div class="clearfix"></div>
           
            <h4><font color="blue"><?php echo $pesan;?></font></h4>
        </header>
        
        <!-- Form Generate Gaji (Hidden) -->
        <div id="formGenerateGaji" style="display:none; margin: 0 0 20px; padding: 20px; background: #fff3cd; border: 2px solid #ffc107; border-radius: 5px;">
            <h4><i class="fa fa-cog"></i> Generate Gaji</h4>
            <form method="POST" action="" onsubmit="return confirm('Apakah Anda yakin ingin generate gaji untuk periode ini? Status semua gaji Draft akan berubah menjadi Generated!')">
                <div class="form-group col-md-4">
                    <label>Pilih Periode <span style="color:red;">*</span></label>
                    <input type="text" name="periode_generate" class="form-control periode-picker" placeholder="YYYY-MM" required>
                </div>
                <div class="form-group col-md-8">
                    <label>&nbsp;</label><br>
                    <button type="submit" name="generate_gaji" class="btn btn-success btn-generate">
                        <i class="fa fa-check"></i> Proses Generate Gaji
                    </button>
                    <button type="button" class="btn btn-default" onclick="hideGenerateForm()">
                        Batal
                    </button>
                </div>
                <div class="clearfix"></div>
            </form>
        </div>
        
        <section class="content">
            <?php /* $periode_terbaru sudah dihitung di atas */ ?>
            <div align="center"><strong>DAFTAR PENGGAJIAN PEGAWAI <?= $periode_terbaru ? '- PERIODE: ' . $periode_terbaru : '' ?></strong></div>

            <div class="box-body">
                <div class="table-responsive">
                    <table id="daftar-gaji" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th style="width:50px">No</th>
                                <th>Nama Pegawai</th>
                                <th>Periode</th>
                                <th>Gaji Pokok</th>
                                <th>Tunj. Jabatan</th>
                                <th>Tunj. Perjalanan</th>
                                <th>Lembur</th>
                                <th>Bonus</th>

                                <th>BPJS TK</th>
                                <th>BPJS Kes</th>
                                <th>Pot. Pinjaman</th>
                                <th>Pot. Lain</th>
                                <th>Total Diterima</th>
                                <th>Bukti TF</th>
                                <th>Status</th>
                                <th style="width:150px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $no = 1;
                        
                        if (!$periode_terbaru) {
                            $q = $conn->prepare("SELECT g.*, ru.nama AS nama_pegawai FROM tgaji g LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser WHERE 1=0");
                            $q->execute();
                        } else {
                            $q = $conn->prepare("
                                SELECT g.*, ru.nama AS nama_pegawai, tj.nama_jabatan
                                FROM tgaji g
                                LEFT JOIN ruser ru ON g.iduser_pegawai = ru.iduser
                                LEFT JOIN tbl_jabatan tj ON ru.kodjab = tj.kodjab
                                WHERE g.periode = :periode
                                ORDER BY ru.kodjab ASC, ru.nama ASC
                            ");
                            $q->execute([':periode' => $periode_terbaru]);
                        }

                        while ($row = $q->fetch()) {
                            $statusBadge = $row['status_gaji'] == 'Generated' ? 
                                '<span class="label label-success">Generated</span>' : 
                                '<span class="label label-warning">Draft</span>';
                        ?>
                            <tr>
                                <td><?= $no ?></td>
                                <td><?= htmlspecialchars($row['nama_pegawai']) ?></td>
                                <td><?= $row['periode'] ?></td>
                                <td align="right"><?= number_format($row['gaji_pokok'], 0, ',', '.') ?></td>
                                <td align="right"><?= number_format($row['tunj_jabatan'], 0, ',', '.') ?></td>
                                <td align="right"><?= number_format($row['tunj_perjalanan'], 0, ',', '.') ?></td>
                                <td align="right">
                                    <?php if ($row['lembur'] > 0): ?>
                                        <a href="index.php?par=37&filter_pegawai=<?= $row['iduser_pegawai'] ?>" title="Tracing Lembur"><?= number_format($row['lembur'], 0, ',', '.') ?></a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td align="right"><?= number_format($row['bonus'], 0, ',', '.') ?></td>
                                <td align="right"><?= number_format($row['bpjs_tk'], 0, ',', '.') ?></td>
                                <td align="right"><?= number_format($row['bpjs_kesehatan'], 0, ',', '.') ?></td>
                                <td align="right">
                                    <?php if ($row['pot_pinjaman'] > 0): ?>
                                        <a href="index.php?par=52&filter_pegawai=<?= $row['iduser_pegawai'] ?>" title="Tracing Pinjaman"><?= number_format($row['pot_pinjaman'], 0, ',', '.') ?></a>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td align="right"><?= number_format($row['pot_lain'], 0, ',', '.') ?></td>
                                <td align="right"><strong><?= number_format($row['total_terima'], 0, ',', '.') ?></strong></td>
                                <td style="text-align:center;">
                                <?php
                                    $buktiTfArr = [];
                                    if (!empty($row['bukti_tf'])) {
                                        $decoded = json_decode($row['bukti_tf'], true);
                                        $buktiTfArr = is_array($decoded) ? $decoded : [$row['bukti_tf']];
                                    }
                                    if (count($buktiTfArr) > 0) {
                                        $buktiTfJson = htmlspecialchars(json_encode($buktiTfArr), ENT_QUOTES);
                                        echo "<button type='button' class='btn btn-info btn-xs' onclick='viewBuktiTfGallery(" . $buktiTfJson . ")'>"
                                           . "<i class='fa fa-image'></i> " . count($buktiTfArr) . " file"
                                           . "</button>";
                                    } else {
                                        echo "<span style='color:#ccc;'>-</span>";
                                    }
                                ?>
                                </td>
                                <td><?= $statusBadge ?></td>
                                <td>
                                    <div class="d-flex" role="group" style="gap: 5px;">
                                        <button type="button"
                                                onclick="openAdjustModal(<?= $row['id'] ?>)"
                                                class="btn btn-warning btn-sm">
                                            Adjust
                                        </button>
                                        <button type="button"
                                                onclick="cetakSlip(<?= $row['id'] ?>)"
                                                class="btn btn-info btn-sm">
                                            Cetak
                                        </button>
                                        <button type="button"
                                                onclick="confirmDelete(<?= $row['id'] ?>)"
                                                class="btn btn-danger btn-sm">
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php
                            $no++;
                        }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>
</div>

<!-- Modal Add Pegawai -->
<div id="modalAddPegawai" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h3><i class="fa fa-plus"></i> Tambah Pegawai ke Daftar Gaji</h3>
        <hr>
        <form method="POST" action="">
            <div class="form-group">
                <label>Pilih Pegawai <span style="color:red;">*</span></label>
                <select name="idpegawai" class="form-control" required>
                    <option value="">-- Pilih Pegawai --</option>
                    <?php
                    $qp = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC");
                    $qp->execute();
                    while($rsp = $qp->fetch()) {
                        echo "<option value='".$rsp['iduser']."'>".$rsp['nama']."</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Periode (Bulan-Tahun) <span style="color:red;">*</span></label>
                <input type="text" name="periode" class="form-control periode-picker" placeholder="YYYY-MM" required>
            </div>
            
            <div class="form-group">
                <label>Gaji Pokok</label>
                <input type="text" name="gaji_pokok" id="add_gaji_pokok" class="form-control input-ribuan" placeholder="0">
            </div>
            
            <div class="form-group">
                <label>Tunjangan Jabatan</label>
                <input type="text" name="tunj_jabatan" id="add_tunj_jabatan" class="form-control input-ribuan" placeholder="0">
            </div>
            
            <div class="form-group">
                <label>Tunjangan Perjalanan Dinas</label>
                <input type="text" name="tunj_perjalanan" id="add_tunj_perjalanan" class="form-control input-ribuan" placeholder="0">
            </div>
            
            <div class="form-group">

            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" name="add_pegawai" class="btn btn-primary">
                    <i class="fa fa-save"></i> Simpan
                </button>
                <button type="button" class="btn btn-default" onclick="closeAddModal()">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import (Pusat Kontrol Excel) -->
<div id="modalImport" class="modal">
    <div class="modal-content" style="max-width: 500px; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.2);">
        <span class="close" onclick="document.getElementById('modalImport').style.display='none'" style="font-size: 28px;">&times;</span>
        <h3 style="color: #2F5597; font-weight: bold;"><i class="fa fa-file-excel-o"></i> Pusat Kontrol Excel</h3>
        <p class="text-muted">Kelola data gaji masal (Mendukung .xls & .xlsx)</p>
        <hr style="border-top: 2px solid #eee;">
        
        <!-- Langkah 1: Unduh -->
        <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #5bc0de;">
            <h5 style="margin-top:0; font-weight:bold;">Langkah 1: Ambil Data</h5>
            <p style="font-size: 13px;">Dapatkan file Excel yang sudah terisi otomatis dengan data pegawai & gaji terakhir.</p>
            <a href="38_export_template.php" class="btn btn-info btn-block" style="border-radius: 20px;">
                <i class="fa fa-download"></i> Unduh Data Excel (Otomatis)
            </a>
        </div>

        <!-- Langkah 2: Unggah -->
        <div style="background: #fdfdfe; padding: 15px; border-radius: 8px; border: 1px dashed #28a745;">
            <h5 style="margin-top:0; font-weight:bold;">Langkah 2: Unggah Perubahan</h5>
            <p style="font-size: 13px;">Setelah file diedit, simpan dan unggah kembali di sini.</p>
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="file" name="file_excel" accept=".xlsx,.xls" required class="form-control" style="margin-bottom: 15px;">
                <button type="submit" name="proses_import" class="btn btn-success btn-block" style="border-radius: 20px; font-weight: bold;">
                    <i class="fa fa-upload"></i> Mulai Perbarui Data
                </button>
            </form>
        </div>

        <div style="margin-top: 15px; text-align: center;">
            <small class="text-muted">* Sistem akan otomatis merapikan tabel & menghitung total gaji.</small>
        </div>
    </div>
</div>

<!-- Modal Adjust Gaji -->
<div id="modalAdjustGaji" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeAdjustModal()">&times;</span>
        <h3><i class="fa fa-edit"></i> Adjust Gaji Pegawai</h3>
        <hr>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="id_gaji" id="adjust_id_gaji">
            
            <div class="form-group">
                <label>Nama Pegawai</label>
                <input type="text" id="adjust_nama" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label>Periode</label>
                <input type="text" id="adjust_periode" class="form-control" readonly>
            </div>
            
            <!-- Komponen Pendapatan -->
            <div class="komponen-section">
                <div class="komponen-header">KOMPONEN PENDAPATAN</div>
                
                <div class="form-group">
                    <label>Gaji Pokok</label>
                    <input type="text" name="gaji_pokok" id="adjust_gaji_pokok" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">
                    <label>Tunjangan Jabatan</label>
                    <input type="text" name="tunj_jabatan" id="adjust_tunj_jabatan" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">
                    <label>Tunjangan Perjalanan Dinas</label>
                    <input type="text" name="tunj_perjalanan" id="adjust_tunj_perjalanan" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">
                    <label>Lembur</label>
                    <input type="text" name="lembur" id="adjust_lembur" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                    <small id="info_lembur_aktif" class="text-muted" style="display:none; margin-top:4px;">
                        <i class="fa fa-info-circle"></i> Lembur disetujui: <strong id="val_lembur_aktif">0</strong> &times; Rp 90.000
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Bonus</label>
                    <input type="text" name="bonus" id="adjust_bonus" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">

                </div>
                
                <div style="margin-top: 10px; font-weight: bold; color: green;">
                    Total Pendapatan: <span id="total_pendapatan">Rp 0</span>
                </div>
            </div>
            
            <!-- Komponen Potongan -->
            <div class="komponen-section">
                <div class="komponen-header">KOMPONEN POTONGAN</div>
                
                <div class="form-group">
                    <label>BPJS Tenaga Kerja</label>
                    <input type="text" name="bpjs_tk" id="adjust_bpjs_tk" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">
                    <label>BPJS Kesehatan</label>
                    <input type="text" name="bpjs_kesehatan" id="adjust_bpjs_kesehatan" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div class="form-group">
                    <label>Potongan Pinjaman</label>
                    <input type="text" name="pot_pinjaman" id="adjust_pot_pinjaman" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                    <small id="info_cicilan_aktif" class="text-muted" style="display:none; margin-top:4px;">
                        <i class="fa fa-info-circle"></i> Cicilan pinjaman aktif: <strong id="val_cicilan_aktif">Rp 0</strong>
                    </small>
                </div>
                
                <div class="form-group">
                    <label>Potongan Lain-lain</label>
                    <input type="text" name="pot_lain" id="adjust_pot_lain" class="form-control input-ribuan" value="0" onkeyup="hitungTotal()">
                </div>
                
                <div style="margin-top: 10px; font-weight: bold; color: red;">
                    Total Potongan: <span id="total_potongan">Rp 0</span>
                </div>
            </div>
            
            <!-- Total Diterima -->
            <div class="total-section">
                <i class="fa fa-money"></i> TOTAL DITERIMA: <span id="total_terima" style="color: #1976d2;">Rp 0</span>
            </div>
            
            <!-- Keterangan & Bukti TF -->
            <div class="komponen-section">
                <div class="komponen-header">KETERANGAN & BUKTI TRANSFER</div>
                
                <div class="form-group">
                    <label>Keterangan</label>
                    <textarea name="keterangan_gaji" id="adjust_keterangan" class="form-control" rows="2" placeholder="Keterangan (opsional)"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Upload Bukti Transfer (JPG/PNG/PDF) — bisa lebih dari 1 file</label>
                    <label class="bukti-tf-upload-area" id="bukti_tf_upload_area">
                        <i class="fa fa-cloud-upload" style="font-size:24px; color:#999;"></i>
                        <span id="bukti_tf_upload_text" style="font-size:12px; color:#999; margin-top:4px;">Klik untuk pilih file bukti transfer</span>
                        <input type="file" name="bukti_tf[]" id="adjust_bukti_tf" multiple accept=".jpg,.jpeg,.png,.gif,.pdf" style="display:none;" onchange="updateBuktiTfLabel(this)">
                        <small id="bukti_tf_count" style="display:none; color:green; font-weight:bold; margin-top:4px;"></small>
                    </label>
                    <div id="adjust_bukti_preview" style="margin-top:8px;"></div>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 20px;">
                <button type="submit" name="adjust_gaji" class="btn btn-success">
                    <i class="fa fa-save"></i> Simpan Perubahan
                </button>
                <button type="button" class="btn btn-default" onclick="closeAdjustModal()">
                    Batal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal View Bukti Transfer (Gallery) -->
<div id="modalBuktiTf" style="display:none; position:fixed; z-index:1100; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.7);">
    <div style="margin:3% auto; max-width:800px; background:#fff; border-radius:8px; padding:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
            <h4 style="margin:0;"><i class="fa fa-image"></i> Bukti Transfer Gaji</h4>
            <span style="font-size:28px; cursor:pointer; color:#aaa; font-weight:bold; line-height:1;" onclick="closeBuktiTfModal()">&times;</span>
        </div>
        <hr style="margin:5px 0 15px;">
        <div id="buktiTfGallery" style="display:flex; flex-wrap:wrap; gap:10px; justify-content:center;"></div>
    </div>
</div>

<script>
// Update bukti TF upload label
function updateBuktiTfLabel(input) {
    var area = input.closest('.bukti-tf-upload-area');
    var text = document.getElementById('bukti_tf_upload_text');
    var count = document.getElementById('bukti_tf_count');
    var fileCount = input.files.length;
    
    if (fileCount > 0) {
        area.classList.add('has-files');
        text.textContent = '';
        count.style.display = 'block';
        count.textContent = fileCount + ' file dipilih';
        area.querySelector('i').style.color = '#4CAF50';
    } else {
        area.classList.remove('has-files');
        text.textContent = 'Klik untuk pilih file bukti transfer';
        count.style.display = 'none';
        area.querySelector('i').style.color = '#999';
    }
}

// View Bukti Transfer Gallery
function viewBuktiTfGallery(files) {
    var gallery = document.getElementById('buktiTfGallery');
    gallery.innerHTML = '';
    
    files.forEach(function(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var item = document.createElement('div');
        item.className = 'bukti-tf-gallery-item';
        
        if (ext === 'pdf') {
            item.innerHTML = '<a href="uploads/bukti_tf/' + filename + '" target="_blank" class="bukti-tf-gallery-pdf">' +
                '<i class="fa fa-file-pdf-o"></i>' +
                '<small>' + filename.substring(0, 20) + '...</small>' +
                '</a>';
        } else {
            item.innerHTML = '<a href="uploads/bukti_tf/' + filename + '" target="_blank">' +
                '<img src="uploads/bukti_tf/' + filename + '" alt="Bukti TF">' +
                '</a>';
        }
        gallery.appendChild(item);
    });
    
    document.getElementById('modalBuktiTf').style.display = 'block';
}

function closeBuktiTfModal() {
    document.getElementById('modalBuktiTf').style.display = 'none';
    document.getElementById('buktiTfGallery').innerHTML = '';
}

// Close modal on outside click
window.addEventListener('click', function(event) {
    var modal = document.getElementById('modalBuktiTf');
    if (event.target == modal) {
        closeBuktiTfModal();
    }
});
</script>


<!-- Form tersembunyi untuk hapus -->
<form id="form_hapus" method="POST" action="" style="display:none;">
    <input type="hidden" name="hapus" value="1">
    <input type="hidden" name="id_hapus" id="id_hapus">
</form>

<script>
function showGenerateForm() {
    document.getElementById('formGenerateGaji').style.display = 'block';
}

function hideGenerateForm() {
    document.getElementById('formGenerateGaji').style.display = 'none';
}

function cetakSlip(id) {
    var iframe = document.getElementById('iframeCetakSlip');
    if (!iframe) {
        iframe = document.createElement('iframe');
        iframe.id = 'iframeCetakSlip';
        iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:600px;height:800px;border:none;';
        document.body.appendChild(iframe);
    }
    iframe.src = '38_cetak_slip_gaji.php?id=' + id;
}

function cetakAllZip(periode) {
    if (!periode) {
        alert('Periode tidak tersedia');
        return;
    }
    window.open('38_cetak_slip_gaji_all.php?periode=' + encodeURIComponent(periode), '_blank');
}
</script>

<script>
function hitungThrOtomatisAdjust() {
    var idPegawai = window._currentIdPegawai;
    var periode = $('#adjust_periode').val();
    var gp = parseRibuan($('#adjust_gaji_pokok').val());
    var tj = parseRibuan($('#adjust_tunj_jabatan').val());
    
    if (!idPegawai) {
        alert("ID Pegawai tidak ditemukan untuk perhitungan THR. Silakan tutup dan buka ulang Adjust.");
        return;
    }
    
    $.ajax({
        url: '38_penggajian.php',
        method: 'GET',
        data: {ajax: 'hitung_thr', idpegawai: idPegawai, periode: periode, gaji_pokok: gp, tunj_jabatan: tj},
        dataType: 'json',
        success: function(res) {
            if (res.error) {
                alert("Error: " + res.error);
            } else {
                $('#adjust_thr').val(formatRibuan(res.thr));
                hitungTotal();
                alert("Masa kerja: " + res.masa_kerja_bulan + " bulan.\nDasar perhitungan upah: Rp " + formatRibuan(res.satuan_upah) + ".\nTHR direkomendasikan: Rp " + formatRibuan(res.thr));
            }
        },
        error: function() {
            alert('Gagal mengambil data perhitungan THR');
        }
    });
}

$(document).ready(function () {
    const tableId = '#daftar-gaji';
    if ($.fn.DataTable.isDataTable(tableId)) {
        $(tableId).DataTable().destroy();
    }

    $(tableId).DataTable({
        paging: false,
        lengthChange: false,
        searching: true,
        ordering: false,
        info: true,
        autoWidth: false,
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ - _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                next: ">",
                previous: "<"
            }
        }
    });
});
</script>
</body>
</html>