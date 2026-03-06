<?php

include "dbase.php";
include "islogin.php";
$pesan = "";

if (isset($_GET['tgl'])) {
    $tgl      = trim($_GET['tgl']);
    $sdtgl    = trim($_GET['sdtgl']);
    $statuslog = isset($_GET['statuslog']) ? trim($_GET['statuslog']) : 'finish'; // Tangkap status log
} else {
    $tgl      = date('Y-m-d', strtotime('first day of this month'));
    $sdtgl    = date('Y-m-d');
    $statuslog = 'finish'; // Default status log
}
?>

<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i>GRAFIK LAPORAN PENYELESAIAN TUGAS</li>
    </ol>
    <section class="panel">
        <header class="panel-heading">
            <form role="form" method="GET" onSubmit="return validasi_input(this)" action="index.php">
                <div class="row">
                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Tanggal : </label>
                        <input name="tgl" id="dp1" type="text" value="<?php echo $tgl; ?>"
                            onKeyPress="if (event.keyCode==13) {dp1.focus()}" size=" 16" class="form-control"
                            value="<?php echo $tgl; ?> ">
                    </div>

                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Sampai Tanggal : </label>
                        <input name="sdtgl" id="dp2" type="text" value="<?php echo $sdtgl ?>" size="16"
                            class="form-control">
                    </div>

                    <div class="form-group col-xs-12 col-sm-2 ">
                        <label>Status Log : </label>
                        <select name="statuslog" id="statuslog" class="form-control">
                            <option value="all" <?php echo ($statuslog == 'all') ? 'selected' : ''; ?>>All</option>
                            <option value="finish" <?php echo ($statuslog == 'finish') ? 'selected' : ''; ?>>Finished
                            </option>
                        </select>
                    </div>

                    <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6" style="margin: 0.1em;">
                        <input type="hidden" name="par" id="par" value="29">

                        <button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
                        <button type="reset" class="btn btn-danger">Reset</button>
                    </div>
                </div>
            </form>
            <div class="clearfix"></div>
            <h4>
                <font color="red"><?php echo $pesan; ?></font>
            </h4>
        </header>
        <section class="content">
            <div class="box-body">
                <div class="flex">
                    <div class="col-md-4" style="display: grid; justify-content: center;">
                        <canvas id="chart-All"></canvas>
                        <a href="" class="btn btn-primary" style="margin-top: 1.5rem;"
                            id="btn-download-all">Download</a>
                    </div>
                    <div class="col-md-8 flex" id="chart-list" style="margin-bottom: 1.5rem;">
                    </div>
                </div>
                <table id="chart-table" class="table table-bordered table-striped table-hover">
                    <thead id="table-header">
                        <!-- <tr>
                            <th>No</th>
                            <th>User</th>
                            <th>Masih Proses</th>
                            <th>Tepat Waktu</th>
                            <th>Terlambat</th>
                            <th>Ket. Terlambat</th>
                            <th>Total Order</th>
                            <th>Nilai</th>
                            <th>Nilai Rata-rata</th>
                        </tr> -->
                    </thead>
                    <tbody id="chart-table-body"></tbody>
                </table>
            </div>
        </section>
    </section>

    <!-- Modal -->
    <div class="modal fade" id="nilaiModal" tabindex="-1" role="dialog" aria-labelledby="nilaiModalLabel"
        aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="nilaiModalLabel">Detail Nilai</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-bordered" id="modal-nilai-table">
                        <thead>
                            <tr>
                                <th>ID Log</th>
                                <th>Mitra</th>
                                <th>Order</th>
                            </tr>
                        </thead>
                        <tbody id="modal-nilai-body">
                            <!-- Data nilai akan ditambahkan di sini -->
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.0.0/dist/chart.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
<!-- <script type="module" src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script> -->
<!-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> -->
<script>
    Chart.register(ChartDataLabels);

    function initChart(element, chartData) {
        let iduser = chartData.userorder
        // let nmUser = chartData.nama
        let totalOrder = chartData.total_order

        // menghilangkan iduser dan nama
        delete chartData.userorder
        delete chartData.ket_terlambat
        delete chartData.ket_nilai
        delete chartData.jml_nilai
        // delete chartData.nama
        delete chartData.total_order
        delete chartData.nilai

        let status = $('select[name="statuslog"]').val();
        let labels = [];
        let data = [];
        let backgroundColor = [];

        if (status === 'finish') {
            labels = [
                'Tepat Waktu: ' + chartData.tepat_waktu,
                'Terlambat: ' + chartData.terlambat
            ];
            data = [
                chartData.tepat_waktu,
                chartData.terlambat,
            ];
            backgroundColor = [
                'rgb(54, 162, 235)',
                'rgb(255, 99, 132)',
            ];
        } else {
            labels = [
                'Tepat Waktu: ' + chartData.tepat_waktu,
                'Terlambat: ' + chartData.terlambat,
                'Proses : ' + chartData.proses
            ];
            data = [
                chartData.tepat_waktu,
                chartData.terlambat,
                chartData.proses
            ];
            backgroundColor = [
                'rgb(54, 162, 235)',
                'rgb(255, 99, 132)',
                'rgb(44, 255, 58)',
            ];
        }

        let chartEl = new Chart(
            element, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Data',
                        data: data,
                        backgroundColor: backgroundColor
                    }]
                },
                options: {
                    tooltips: {
                        enabled: false
                    },
                    plugins: {
                        datalabels: {
                            anchor: 'center',
                            formatter: function(value, ctx) {

                                let sum = 0;
                                let dataArr = ctx.dataset.data;

                                dataArr.map(item => {
                                    sum = parseInt(sum) + parseInt(item)
                                })


                                let percent = (value * 100 / sum)
                                return percent == 0 ? '' : percent.toFixed(2) + '%';
                            },
                            color: "#fff"
                        },
                        legend: {
                            position: 'top'
                        },
                        title: {
                            display: true,
                            text: [
                                iduser ?? "Rata Keterlambatan",
                                'Total Order: ' + totalOrder
                            ]
                        }
                    },
                    animation: {
                        onComplete: function(animation) {
                            if (iduser != 'All') {
                                $(`#btn-download-${iduser}`).attr('href', chartEl.toBase64Image()).attr('download',
                                    `Chart ${iduser}.png`)
                            } else {
                                $(`#btn-download-all`).attr('href', chartEl.toBase64Image()).attr('download',
                                    `Chart All.png`)

                            }
                        }
                    }
                }
            }
        )
    }

    function updateTableHeaders() {
        let status = $('select[name="statuslog"]').val();
        const headerRow = $('#table-header');
        headerRow.empty();

        if (status === 'finish') {
            headerRow.append(
                '<tr><th>No</th><th>User</th><th>Tepat Waktu</th><th>Terlambat</th><th>Ket. Terlambat</th><th>Total Order</th><th>Nilai</th><th>Nilai Rata-rata</th></tr>'
            );
        } else {
            headerRow.append(
                '<tr><th>No</th><th>User</th><th>Masih Proses</th><th>Tepat Waktu</th><th>Terlambat</th><th>Ket. Terlambat</th><th>Total Order</th><th>Nilai</th><th>Nilai Rata-rata</ </th></tr>'
            );
        }
    }

    function getData() {
        let params = {
            tglAwal: $('input[name="tgl"]').val(),
            tglAkhir: $('input[name="sdtgl"]').val(),
            status: $('select[name="statuslog"]').val(),
        };

        let status = params.status;
        params = new URLSearchParams(params).toString();

        $.ajax({
            url: "29a_grafik_laporan_data.php?" + params,
            method: "GET",
            dataType: "json",
            success: function(res) {
                console.log("Parsed JSON:", res); // sudah jadi array/object                $('#chart-list').empty();
                if (res.status === "ok") {
                    $('#chart-list').empty();
                    $('#chart-table-body').empty();

                    let no = 1;
                    res.data.forEach((data) => {
                        if (data.userorder !== 'All') {
                            $('#chart-list').append(`<div class="col-md-4" style="display: grid; justify-content: center;">
                            <canvas id="chart-${data.userorder}"></canvas>
                            <a href="" class="btn btn-primary" style="margin-top: 1.5rem;" id="btn-download-${data.userorder}">Download</a>
                        </div>`);
                        }
                        let nilaiEl = ``;
                        data.nilai.forEach((nilai) => {
                            nilaiEl +=
                                `<div class="nilai-item" data-idlog="${nilai.idlog}" data-mitra="${nilai.nmcustomer}" data-order="${nilai.desorder}" style="cursor: pointer;">${nilai.nilai}: ${nilai.jml_nilai}</div>`;
                        });

                        if (status == 'finish') {
                            if (data.userorder == "All") {
                                $('#chart-table-body').append(`<tr style="background-color:#8b8b8b; color: white;">
                                <td colspan="2" style="text-align:right;">${data.userorder}</td>
                                <td>${data.tepat_waktu}</td>
                                <td>${data.terlambat}</td>
                                <td></td>
                                <td>${data.total_order}</td>
                                <td colspan="2">${nilaiEl}</td>
                            </tr>`);
                            } else {
                                $('#chart-table-body').append(`<tr>
                                <td>${no++}</td>
                                <td>${data.userorder}</td>
                                <td>${data.tepat_waktu}</td>
                                <td>${data.terlambat}</td>
                                <td>${data.ket_terlambat}</td>
                                <td>${data.total_order}</td>
                                <td>${nilaiEl}</td>
                                <td>${data.ket_nilai} (${data.jml_nilai})</td>
                            </tr>`);
                            }
                        } else {
                            if (data.userorder == "All") {
                                $('#chart-table-body').append(`<tr style="background-color:#8b8b8b; color: white;">
                                <td colspan="2" style="text-align:right;">${data.userorder}</td>
                                <td>${data.proses}</td>
                                <td>${data.tepat_waktu}</td>
                                <td>${data.terlambat}</td>
                                <td></td>
                                <td>${data.total_order}</td>
                                <td colspan="2">${nilaiEl}</td>
                            </tr>`);
                            } else {
                                $('#chart-table-body').append(`<tr>
                                <td>${no++}</td>
                                <td>${data.userorder}</td>
                                <td>${data.proses}</td>
                                <td>${data.tepat_waktu}</td>
                                <td>${data.terlambat}</td>
                                <td>${data.ket_terlambat}</td>
                                <td>${data.total_order}</td>
                                <td>${nilaiEl}</td>
                                <td>${data.ket_nilai} (${data.jml_nilai})</td>
                            </tr>`);
                            }
                        }

                        $('.nilai-item').on('click', function() {
                            // const nilai = $(this).data('nilai');
                            // const jmlNilai = $(this).data('jml-nilai');
                            const notiket = $(this).data('idlog');
                            const mitra = $(this).data('mitra');
                            const order = $(this).data('order');

                            // Kosongkan isi modal
                            $('#modal-nilai-body').empty();
                            $('#modal-nilai-body').append(
                                `<tr><td>${notiket}</td><td>${mitra}</td><td>${order}</td></tr>`);

                            // Tampilkan modal
                            $('#nilaiModal').modal('show');
                        });

                        initChart(
                            document.getElementById('chart-' + data.userorder),
                            data
                        );
                    });
                } else {
                    console.error("Server Error:", res.message);
                }
            },
            error: function(xhr, status, error) {
                console.log("AJAX error:", error);
                console.log("Response Text:", xhr.responseText);
            }
        });
        // $(window).on('click', function(e){
        //     // console.log(e.target);
        //     if($(e.target).hasClass('nilai-item')){
        //         const nilai = $(e.target).text().split(':')[0].trim();
        //         console.log(nilai);
        //         const detailData = JSON.parse(nilai);
        //         $('#modal-nilai-body').empty();
        //         detailData.forEach(item => {
        //             $('#modal-nilai-body').append(`
        //                 <tr>
        //                     <td>${item.idlog}</td>
        //                     <td>${item.nmcustomer}</td>
        //                     <td>${item.desorder}</td>
        //                     <td>${item.ketterlambat}</td>
        //                 </tr>
        //             `);
        //         });
        //         $('#nilaiModal').modal('show');
        //     }
        // });
        // $('.nilai-item').each(function(i, el){
        //     $(el).on('click', function() {
        //         const nilai = $(this).text().split(':')[0].trim();
        //         console.log(nilai);

        //     });
        // });

    }

    $(document).ready(function() {
        updateTableHeaders();
        getData()
    })
</script>