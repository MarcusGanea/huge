<div class="container">
    <h1>MessengerController/index</h1>
    <div class="box">

        <!-- echo out the system feedback (error and success messages) -->
        <?php $this->renderFeedbackMessages(); ?>

        <h3>What happens here ?</h3>
        <div>
            This controller/action/view shows a list of all your chats.
        </div>
        <button>
            <a href="<?= Config::get('URL') . 'messenger/new'; ?>">New Chat</a>
        </button>
        <div>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">

    <table id="myTable" class="display stripe hover">
        <thead>
            <tr>
                <th>Chat-ID</th>
                <th>Partner</th>
                <th>E-Mail</th>
                <th>Öffnen</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->chats as $chat) { ?>
                <tr>
                    <td><?= $chat->chat_id; ?></td>
                    <td><?= $chat->partner_name; ?></td>
                    <td><?= $chat->partner_email; ?></td>
                    <td>
                    <a href="<?= Config::get('URL') . 'messenger/chat/' . $chat->partner_id; ?>">Chat öffnen</a>
                </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
    <script>
        $(function () {
            new DataTable('#myTable', {
                paging: true,
                searching: true,
                ordering: true,
                info: true,
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, searchable: false, targets: [3] }
                ],
                language: {
                    search: 'Suche:',
                    lengthMenu: '_MENU_ Einträge pro Seite',
                    info: '_START_ bis _END_ von _TOTAL_ Einträgen',
                    zeroRecords: 'Keine passenden Einträge gefunden',
                    emptyTable: 'Keine Daten vorhanden',
                    paginate: {
                        first: 'Erste',
                        last: 'Letzte',
                        next: 'Weiter',
                        previous: 'Zurück'
                    }
                }
            });
        });
    </script>
    </div>
    </div>
</div>