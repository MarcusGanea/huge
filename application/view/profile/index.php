<div class="container">
    <h1>ProfileController/index</h1>
    <div class="box">

        <!-- echo out the system feedback (error and success messages) -->
        <?php $this->renderFeedbackMessages(); ?>

        <h3>What happens here ?</h3>
        <div>
            This controller/action/view shows a list of all users in the system. You could use the underlying code to
            build things that use profile information of one or multiple/all users.
        </div>
        <div>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">

    <table id="myTable" class="display stripe hover">
        <thead>
            <tr>
                <th>Id</th>
                <th>Avatar</th>
                <th>Username</th>
                <th>User's email</th>
                <th>Activated?</th>
                <th>Profile</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->users as $user) { ?>
                <tr class="<?= ($user->user_active == 0 ? 'inactive' : 'active'); ?>">
                    <td><?= $user->user_id; ?></td>
                    <td class="avatar">
                        <?php if (isset($user->user_avatar_link)) { ?>
                            <img src="<?= $user->user_avatar_link; ?>" />
                        <?php } ?>
                    </td>
                    <td><?= $user->user_name; ?></td>
                    <td><?= $user->user_email; ?></td>
                    <td><?= ($user->user_active == 0 ? 'No' : 'Yes'); ?></td>
                    <td>
                        <a href="<?= Config::get('URL') . 'profile/showProfile/' . $user->user_id; ?>">Profile</a>
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
                    { orderable: false, searchable: false, targets: [1, 5] }
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
