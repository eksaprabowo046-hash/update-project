<?php
// 
include "dbase.php";
$pesan = "";

// ambil enum
$sqldesc = "DESC tlog";
$qdesc = $conn->prepare($sqldesc);
$qdesc->execute();
$fieldAll = $qdesc->fetchAll();

$enumFields = array();
foreach ($fieldAll as $value) {
	if (strpos($value['Type'], 'enum') !== false) {
		// Ekstrak nilai enum dari string Type
		preg_match_all("/'([^']*)'/", $value['Type'], $matches);
		$enumFields[$value['Field']] = $matches[1]; // Menyimpan sebagai array
	}
}

if (isset($_GET['tgl'])) {
    $tgl      = trim($_GET['tgl']);  
    $sdtgl    = trim($_GET['sdtgl']); 
} else {
    $tgl      = date('Y-m-d', strtotime('first day of this month'));  
    $sdtgl    = date('Y-m-d');
}

?>

<style>
.bulk-bar {
    background: linear-gradient(135deg, #e8f0fe, #d2e3fc);
    border: 1px solid #a8c7fa;
    border-radius: 8px;
    padding: 12px 18px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    box-shadow: 0 2px 6px rgba(66,133,244,0.12);
}
.bulk-bar .bulk-info {
    font-size: 14px;
    color: #1a73e8;
    font-weight: 700;
    min-width: 130px;
}
.bulk-bar .bulk-info span {
    background: #1a73e8;
    color: #fff;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 14px;
}
.bulk-bar select {
    max-width: 180px;
    border-color: #a8c7fa;
}
.row-checked {
    background-color: #e3f2fd !important;
}
.row-saved {
    background-color: #e8f5e9 !important;
}
.cb-nilai { cursor: pointer; width: 18px; height: 18px; }
.badge-tersimpan {
    display: inline-block;
    background: #43a047;
    color: #fff;
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-top: 4px;
    animation: fadeInBadge 0.3s ease;
}
@keyframes fadeInBadge {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}
/* Rich HTML preview di tabel */
.rich-preview { max-height: 80px; overflow: hidden; font-size: 12px; line-height: 1.4; color: #555; }
.rich-preview p { margin: 0 0 2px 0; }
.rich-preview strong, .rich-preview b { font-weight: bold; }
.rich-preview em, .rich-preview i { font-style: italic; }
.rich-preview u { text-decoration: underline; }
.rich-preview a { color: #337ab7; text-decoration: underline; }
.rich-preview ul, .rich-preview ol { margin: 0 0 2px 14px; padding: 0; }
.rich-preview img { max-width: 60px; max-height: 50px; border: 1px solid #ddd; border-radius: 3px; vertical-align: middle; }
</style>

<body>
    <div class="row">
        <ol class="breadcrumb">
            <li><i class="fa fa-home"></i>LAPORAN BELUM DINILAI</li>
        </ol>
        <section class="panel">
            <header class="panel-heading">
                <form role="form" method="GET" action="index.php">
                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Tanggal : </label>
                        <input name="tgl" id="dp1" type="text" size="16" class="form-control" value="<?php echo $tgl; ?>">
                    </div>
                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Sampai Tanggal : </label>
                        <input name="sdtgl" id="dp2" type="text" size="16" class="form-control" value="<?php echo $sdtgl; ?>">
                    </div>
                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Customer </label>
                        <select name="kodcustomer" id="kodcustomer" class="form-control">
                            <option value="" selected>All</option>
                            <?php
							$qk = $conn->prepare("SELECT * FROM rcustomer ORDER BY kodcustomer ");
							$qk->execute();
							while ($rsk = $qk->fetch()) {
								$selected = (isset($_GET['kodcustomer']) && $_GET['kodcustomer'] == $rsk['kodcustomer']) ? "SELECTED" : "";
								echo "<option value='" . $rsk['kodcustomer'] . "' $selected>" . $rsk['nmcustomer'] . "</option>";
							}
						?>
                        </select>
                    </div>
                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Dikerjakan Oleh</label>
                        <select name="dikerjakan" id="dikerjakan" class="form-control">
                            <option value="" selected>All</option>
                            <?php
						$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
						$qk->execute(); 
						while($rsk = $qk->fetch()){ 
							$selected = (isset($_GET['dikerjakan']) && $_GET['dikerjakan'] == $rsk['iduser']) ? "SELECTED" : "";
							echo "<option value='{$rsk['iduser']}' {$selected}>{$rsk['nama']}</option>\n"; 
						}
					?>
                        </select>
                    </div>
				<div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
					<input type="hidden" name="par" id="par" value="30">
					<button type="submit" name="submit" class="btn btn-primary" value="Y">Tampilkan</button>
					<button type="reset" class="btn btn-danger">Reset</button>
				</div>
    		</form>
			<div class="clearfix"> </div>
			<h4>
				<span color="red"><?php echo $pesan; ?></span>
			</h4>
		</header>
    <section class="content">

        <!-- Bulk Rating Bar -->
        <div class="bulk-bar" id="bulkBar">
            <div class="bulk-info">
                <span id="checkedCount">0</span> data dipilih
            </div>
            <select id="bulkNilai" class="form-control">
                <option value="" disabled selected>-- Pilih Nilai --</option>
                <?php
                if (isset($enumFields['nilai'])) {
                    foreach ($enumFields['nilai'] as $value) {
                        $name = nameKolomNilai($value);
                        echo "<option value='{$value}'>{$name}</option>";
                    }
                }
                ?>
            </select>
            <button type="button" class="btn btn-success btn-sm" onclick="simpanNilaiBulk()">
                <i class="fa fa-check"></i> Simpan Nilai Terpilih
            </button>
            <button type="button" class="btn btn-default btn-sm" onclick="resetPilihan()">
                <i class="fa fa-times"></i> Batal
            </button>
        </div>

        <div class="box-body">
            <table id="contoh" class="table table-bordered table-striped table-hover">
                <div>
                    <thead>
                        <tr class="height: 5px;">
                            <th style="width:35px;"><input type="checkbox" id="checkAll" class="cb-nilai" title="Pilih Semua"></th>
                            <th>No</th>
                            <th>Ticket By</th>
                            <th>Dikerjakan</th>
                            <th>Mitra</th>
                            <th>FasOrder</th>
                            <th>Tgl Order</th>
                            <th>Tgl Target</th>
                            <th>Tgl Selesai</th>
                            <th>Order</th>
                            <th>Order Layanan</th>
                            <th>Ket. Terlambat</th>
                            <th>Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <div id="show-product">
                        </div>
                        <?php
						if ($_GET[''] !== "") {
							$kodcustomer = ($_GET['kodcustomer']);
							$tgl 		= ($_GET['tgl']);
							$sdtgl 		= ($_GET['sdtgl']);
							$iduser   	= $_SESSION['DEFAULT_IDUSER'];
							$dikerjakan  = isset($_GET['dikerjakan']) ? $_GET['dikerjakan'] : '';
							$swhere = "AND (a.tglorder BETWEEN '$tgl' AND '$sdtgl')";

							if ($kodcustomer != "") {
								$swhere .= " AND a.kodcustomer = '$kodcustomer'";
							}

							if ($dikerjakan != "") {
								$swhere .= " AND a.userorder = '$dikerjakan'";
							}
							try {
								$strsql = "SELECT a.idlog,a.userorder, a.iduser, b.nmcustomer,a.fasorder, a.tglorder,a.tgltarget, a.tglselesai, a.desorder, a.deslayan, a.tglselesai, a.prioritas, a.ketterlambat, nilai
										FROM tlog a 
										INNER JOIN rcustomer b  ON a.kodcustomer =b.kodcustomer 
										WHERE nilai IS NULL {$swhere} AND a.isselesai = 1
										ORDER BY a.tgltarget DESC;";
								$sql = $conn->prepare($strsql);

								$sql->execute();
								// tampilkan
								$no = 1;
								while ($rs = $sql->fetch()) {

									$option = "<option value='' disabled selected>Pilih</option>";
									foreach ($enumFields['nilai'] as $key => $value) {
										$selected = ($value == $rs['nilai']) ? "selected" : "";
										$name = nameKolomNilai($value);
										$option .= "<option value='" . $value . "' $selected>" . $name . "</option>";
									}
									$selectNilai = "<select name='nilai' class='form-control nilai-select' data-idlog='" . $rs['idlog'] . "' onchange=\"pilihData('" . $rs['idlog'] . "', this.value)\">{$option}</select>";
									echo "  <tr id='row-" . $rs['idlog'] . "'>
											<td align=center><input type='checkbox' class='cb-nilai cb-row' value='" . $rs['idlog'] . "'></td>
											<td align=center><font size=-1>" . $no . "</font></td>
											<td><font size=-1 >" . $rs['idlog'] . " | " . $rs['iduser'] . "</font></td>
											<td><font size=-1 >" . $rs['userorder'] . "</font></td>
											<td><font size=-1 >" . $rs['nmcustomer'] . "</font></td>
											<td><font size=-1 >" . $rs['fasorder'] . "</font></td>
											<td><font size=-1 >" . $rs['tglorder'] . "</font></td>
											<td><font size=-1 >" . $rs['tgltarget'] . "</font></td>
											<td><font size=-1 >" . $rs['tglselesai'] . "</font></td>
											<td><font size=-1 ><div class='rich-preview'>".stripslashes($rs['desorder'])."</div></font></td>
											<td><font size=-1 ><div class='rich-preview'>".stripslashes($rs['deslayan'])."</div></font></td>
											<td><font size=-1 ><div class='rich-preview'>".stripslashes($rs['ketterlambat'])."</div></font></td>
											<td><div id='nilai-cell-" . $rs['idlog'] . "'>" . $selectNilai . "</div></td>
										</tr>";

									$no++;
								}
							} //try
							catch (PDOException $e) {
								echo "  
				   <tr>
					</tr>  ";
							} //catch 
						}
						?>

                    </tbody>
            </table>
        </div>
    </section>
    </section>

    </div>

    <script>
    var namaMap = {'1':'Evaluasi','2':'Kurang Baik','3':'Normal','4':'Baik','5':'Sangat Baik'};

    // === Penilaian individual ===
    function pilihData(idlog, nilai) {
        fetch('30_lap_nilai_source.php?idlog=' + idlog + '&nilai=' + nilai)
            .then(response => response.text())
            .then(function() {
                markSaved(idlog, nilai);
            });
    }

    // === Tandai baris sebagai tersimpan ===
    function markSaved(idlog, nilai) {
        var row = document.getElementById('row-' + idlog);
        var cell = document.getElementById('nilai-cell-' + idlog);
        if (row) {
            row.classList.add('row-saved');
        }
        if (cell) {
            // Ganti dropdown dengan badge tersimpan
            var label = namaMap[nilai] || nilai;
            cell.innerHTML = '<span style="font-weight:600;">' + label + '</span>' +
                '<br><span class="badge-tersimpan"><i class="fa fa-check"></i> Tersimpan</span>';
        }
    }

    // === Checkbox: Select All ===
    document.getElementById('checkAll').addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('.cb-row');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = this.checked;
            highlightRow(checkboxes[i]);
        }
        updateCount();
    });

    // === Checkbox: per baris ===
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('cb-row')) {
            highlightRow(e.target);
            updateCount();
            // Update "Select All" state
            var all = document.querySelectorAll('.cb-row');
            var checked = document.querySelectorAll('.cb-row:checked');
            document.getElementById('checkAll').checked = (all.length === checked.length && all.length > 0);
        }
    });

    // === Highlight baris yang dicentang ===
    function highlightRow(cb) {
        var row = cb.closest('tr');
        if (cb.checked) {
            row.classList.add('row-checked');
        } else {
            row.classList.remove('row-checked');
        }
    }

    // === Update jumlah yang dipilih ===
    function updateCount() {
        var checked = document.querySelectorAll('.cb-row:checked');
        document.getElementById('checkedCount').textContent = checked.length;
    }

    // === Simpan Nilai Bulk ===
    function simpanNilaiBulk() {
        var checked = document.querySelectorAll('.cb-row:checked');
        var nilai = document.getElementById('bulkNilai').value;

        if (checked.length === 0) {
            alert('Pilih minimal 1 data untuk dinilai!');
            return;
        }
        if (!nilai) {
            alert('Pilih nilai terlebih dahulu!');
            return;
        }

        var namaMap = {'1':'Evaluasi','2':'Kurang Baik','3':'Normal','4':'Baik','5':'Sangat Baik'};
        if (!confirm('Beri nilai "' + (namaMap[nilai] || nilai) + '" untuk ' + checked.length + ' data terpilih?')) {
            return;
        }

        // Kumpulkan semua idlog
        var idlogs = [];
        for (var i = 0; i < checked.length; i++) {
            idlogs.push(checked[i].value);
        }

        // Kirim bulk request
        fetch('30_lap_nilai_source.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'bulk=1&nilai=' + nilai + '&idlogs=' + idlogs.join(',')
        })
        .then(response => response.text())
        .then(function(data) {
            // Feedback visual: baris hijau + badge tersimpan
            for (var i = 0; i < checked.length; i++) {
                markSaved(checked[i].value, nilai);
                var row = document.getElementById('row-' + checked[i].value);
                if (row) row.classList.remove('row-checked');
                checked[i].checked = false;
            }
            document.getElementById('checkAll').checked = false;
            document.getElementById('bulkNilai').selectedIndex = 0;
            updateCount();
            alert('Berhasil! ' + idlogs.length + ' data telah dinilai.');
        })
        .catch(function(err) {
            alert('Error: ' + err);
        });
    }

    // === Reset pilihan ===
    function resetPilihan() {
        var checkboxes = document.querySelectorAll('.cb-row');
        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].checked = false;
            highlightRow(checkboxes[i]);
        }
        document.getElementById('checkAll').checked = false;
        document.getElementById('bulkNilai').selectedIndex = 0;
        updateCount();
    }
    </script>
</body>

</html>