<?php

/**
 * GroupController
 * Handles group chat creation, joining, and messaging.
 * Reuses MessengerModel::getMessagesByChatId() and MessengerModel::markChatAsRead().
 */
class GroupController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        Auth::checkAuthentication();
    }

    /**
     * Overview: shows the user's groups and groups available to join.
     */
    public function index()
    {
        $this->View->render('group/index', [
            'my_groups'        => GroupModel::getMyGroupChats(),
            'available_groups' => GroupModel::getAvailableGroupChats()
        ]);
    }

    /**
     * Show the "create group" form.
     */
    public function create()
    {
        $this->View->render('group/create');
    }

    /**
     * Process the group creation form (POST).
     */
    public function doCreate()
    {
        $group_name = trim(Request::post('group_name', true));

        if (empty($group_name)) {
            Session::add('feedback_negative', 'Bitte gib einen Gruppennamen ein.');
            Redirect::to('group/create');
            return;
        }

        $chat_id = GroupModel::createGroupChat((int) Session::get('user_id'), $group_name);

        if ($chat_id) {
            Session::add('feedback_positive', 'Gruppe wurde erfolgreich erstellt.');
            Redirect::to('group/chat/' . $chat_id);
        } else {
            Session::add('feedback_negative', 'Gruppe konnte nicht erstellt werden.');
            Redirect::to('group/create');
        }
    }

    /**
     * Join an existing group chat and redirect to its chat view.
     *
     * @param int $chat_id
     */
    public function join($chat_id)
    {
        $chat_id = (int) $chat_id;

        if (GroupModel::joinGroupChat((int) Session::get('user_id'), $chat_id)) {
            Session::add('feedback_positive', 'Du bist der Gruppe beigetreten.');
        } else {
            Session::add('feedback_negative', 'Beitreten nicht möglich.');
        }

        Redirect::to('group/chat/' . $chat_id);
    }

    /**
     * Show the group chat window.
     *
     * @param int $chat_id
     */
    public function chat($chat_id)
    {
        $chat_id         = (int) $chat_id;
        $current_user_id = (int) Session::get('user_id');

        if (!GroupModel::isUserInChat($current_user_id, $chat_id)) {
            Session::add('feedback_negative', 'Du bist kein Mitglied dieser Gruppe.');
            Redirect::to('group/index');
            return;
        }

        $group = GroupModel::getGroupChatData($chat_id);

        if (!$group) {
            Redirect::to('group/index');
            return;
        }

        MessengerModel::markChatAsRead($chat_id, $current_user_id);

        $this->View->render('group/chat', [
            'my_groups'      => GroupModel::getMyGroupChats(),
            'non_members'    => GroupModel::getNonMembersForGroup($chat_id),
            'messages'       => MessengerModel::getMessagesByChatId($chat_id, $current_user_id),
            'active_chat_id' => $chat_id,
            'group'          => $group
        ]);
    }

    /**
     * Send a message to a group chat (POST).
     */
    public function send()
    {
        $chat_id = (int) Request::post('chat_id');
        $content = Request::post('content', true);

        GroupModel::sendMessageToGroup(
            (int) Session::get('user_id'),
            $chat_id,
            $content
        );

        Redirect::to('group/chat/' . $chat_id);
    }

    /**
     * Add a member to a group chat (POST).
     */
    public function doAddMember()
    {
        $chat_id = (int) Request::post('chat_id');
        $user_id = (int) Request::post('user_id');

        if (!GroupModel::isUserInChat((int) Session::get('user_id'), $chat_id)) {
            Session::add('feedback_negative', 'Keine Berechtigung.');
            Redirect::to('group/index');
            return;
        }

        if ($user_id && GroupModel::addMemberToGroup($chat_id, $user_id)) {
            Session::add('feedback_positive', 'Mitglied wurde hinzugefügt.');
        } else {
            Session::add('feedback_negative', 'Mitglied konnte nicht hinzugefügt werden.');
        }

        Redirect::to('group/chat/' . $chat_id);
    }
}
