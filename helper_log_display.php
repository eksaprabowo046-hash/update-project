<?php
/**
 * Helper: tampilkan preview HTML rich-content + tombol "selengkapnya" untuk kolom desorder/deslayan.
 * 
 * Cara pakai:
 *   include_once 'helper_log_display.php';
 *   // dalam loop tabel:
 *   echo renderLogColumn($rs['desorder'], 'desorder', $rs['idlog'], 'Uraian Order');
 *   echo renderLogColumn($rs['deslayan'], 'deslayan', $rs['idlog'], 'Aktivitas Layanan');
 */

function renderLogColumn($raw, $prefix, $idlog, $title) {
    $html  = stripslashes((string)$raw);
    $plain = trim(strip_tags($html));
    $isLong = mb_strlen($plain) > 100 || stripos($html, '<img') !== false;

    $divId = $prefix . '_' . $idlog;

    $out  = "<div class='rich-preview'>{$html}</div>";
    if ($isLong) {
        $out .= "<a href='#' class='btn-lihat-konten' data-target='#konten_{$divId}' data-title='{$title}' title='Lihat selengkapnya'>";
        $out .= "<i class='fa fa-expand text-primary'></i> selengkapnya</a>";
        $out .= "<div id='konten_{$divId}' style='display:none;'>{$html}</div>";
    }
    return $out;
}

function renderLogModal() {
    return '
<!-- Modal lightbox untuk konten lengkap -->
<div class="modal fade" id="imgModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title">Konten Lengkap</h4>
      </div>
      <div class="modal-body"></div>
    </div>
  </div>
</div>

<style>
/* Rich HTML preview di kolom tabel */
.rich-preview { max-height: 80px; overflow: hidden; font-size: 12px; line-height: 1.5; color: #555; }
.rich-preview p { margin: 0 0 2px 0; }
.rich-preview strong, .rich-preview b { font-weight: bold; }
.rich-preview em { font-style: italic; }
.rich-preview u { text-decoration: underline; }
.rich-preview s { text-decoration: line-through; }
.rich-preview a { color: #337ab7; text-decoration: underline; }
.rich-preview ul, .rich-preview ol { margin: 0 0 2px 14px; padding: 0; }
.rich-preview li { margin-bottom: 1px; }
.rich-preview img { max-width: 60px; max-height: 48px; border: 1px solid #ddd; border-radius: 3px; vertical-align: middle; }
/* Tombol selengkapnya */
.btn-lihat-konten { font-size: 11px; color: #337ab7; }
.btn-lihat-konten:hover { text-decoration: underline; }
/* Modal konten lengkap */
#contoh td img { max-width: 80px; max-height: 60px; cursor: pointer; border: 1px solid #ddd; border-radius: 3px; padding: 2px; }
#imgModal .modal-body { max-height: 65vh; overflow-y: auto; padding: 18px; }
#imgModal .modal-body img { max-width: 100%; max-height: 400px; height: auto; display: block; margin: 0 auto 10px auto; border-radius: 4px; border: 1px solid #ddd; }
#imgModal .modal-body p { margin-bottom: 4px; }
</style>

<script>
$(document).on("click", ".btn-lihat-konten", function(e) {
    e.preventDefault();
    var target = $(this).data("target");
    var title  = $(this).data("title") || "Konten Lengkap";
    var html   = $(target).html();
    $("#imgModal .modal-title").text(title);
    $("#imgModal .modal-body").html(html);
    $("#imgModal").modal("show");
});
$("#imgModal").on("hidden.bs.modal", function() { $("#imgModal .modal-body").html(""); });
</script>';
}
?>

