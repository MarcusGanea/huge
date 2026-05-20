<div class="container">
    <h1>Admin/index</h1>

    <div class="box">

        <!-- echo out the system feedback (error and success messages) -->
        <?php $this->renderFeedbackMessages(); ?>

        <h3>What happens here ?</h3>

        <div>
            This controller/action/view shows a list of all users in the system. with the ability to soft delete a user
            or suspend a user.
        </div>
        <div>
            <link rel="stylesheet" href="https://cdn.datatables.net/2.3.8/css/dataTables.dataTables.min.css">

            <table id="adminTable">
                <thead>
                <tr>
                    <th>Id</th>
                    <th>Avatar</th>
                    <th>Username</th>
                    <th>User's email</th>
                    <th>Activated ?</th>
                    <th>Profile</th>
                    <th>Suspend</th>
                    <th>Role</th>
                    <th>Delete</th>
                    <th>Submit</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($this->users as $user) { ?>
                    <?php $formId = 'account-settings-' . $user->user_id; ?>
                    <tr class="<?= ($user->user_active == 0 ? 'inactive' : 'active'); ?>">
                        <td><?= $user->user_id; ?></td>
                        <td class="avatar">
                            <?php if (isset($user->user_avatar_link)) { ?>
                                <img src="<?= $user->user_avatar_link; ?>"/>
                            <?php } ?>
                        </td>
                        <td><?= $user->user_name; ?></td>
                        <td><?= $user->user_email; ?></td>
                        <td><?= ($user->user_active == 0 ? 'No' : 'Yes'); ?></td>
                        <td>
                            <a href="<?= Config::get('URL') . 'profile/showProfile/' . $user->user_id; ?>">Profile</a>
                        </td>
                        <td><input type="number" name="suspension" form="<?= $formId; ?>" /></td>
                        <td><select name="roles" form="<?= $formId; ?>">
                                    <?php foreach ($this->roles as $role) { ?>
                                        <option value="<?= $role->user_role_id; ?>" <?= ((int) $user->user_account_type === (int) $role->user_role_id ? 'selected' : ''); ?>><?= $role->user_role_name; ?></option>
                                    <?php } ?>
                                </select>
                        </td>
                        <td><input type="checkbox" name="softDelete" form="<?= $formId; ?>" <?php if ($user->user_deleted) { ?> checked <?php } ?> /></td>
                        <td>
                            <form id="<?= $formId; ?>" action="<?= config::get("URL"); ?>admin/actionAccountSettings" method="post">
                                <input type="hidden" name="user_id" value="<?= $user->user_id; ?>" />
                                <input type="submit" value="Senden" />
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
            <script src="https://cdn.datatables.net/2.3.8/js/dataTables.min.js"></script>
            <script>
                $(function () {
                    new DataTable('#adminTable', {
                        scrollX: true,
                        pageLength: 5,
                        lengthMenu: [5, 10, 25],
                        order: [[0, 'asc']],
                        columnDefs: [
                            { orderable: false, searchable: false, targets: [1, 5, 6, 7, 8, 9] }
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
