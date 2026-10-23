$(document).ready(function () {
    var table = $('#datatable-buttons').DataTable({

        lengthChange: false,
        orientation: 'landscape',
        pageSize: 'A0',
        buttons: ['excel',
            {
                extend: 'pdfHtml5',
                exportOptions: {
                    columns: "thead th:not(.noExport)",
                    visible: false
                },
                customize: function (doc) {

                    doc.styles.tableHeader.alignment = 'center';
                    doc.styles.tableBodyEven.alignment = 'center';
                    doc.styles.tableBodyOdd.alignment = 'center';
                }
            },
            {
                extend: 'print',
                exportOptions: {
                    columns: "thead th:not(.noExport)",
                    visible: false
                }
            },
            'colvis'
        ]

    });


    table.buttons().container()
            .appendTo('#datatable-buttons_wrapper .col-md-6:eq(0)');
});