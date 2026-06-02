<?php

class MessengerController extends Controller
{
    /**
     * Construct this object by extending the basic Controller class
     */
    public function __construct()
    {
        parent::__construct();
        Auth::checkAuthentication();
    }

    /**
     * Handles what happens when user moves to URL/index/index - or - as this is the default controller, also
     * when user moves to /index or enter your application at base level
     */
    public function index()
    {
        $this->View->render('messenger/index', array(
            //'users' => UserModel::getPublicProfilesOfAllUsers())
            //'chats' => MessengerModel::getAllChats())
            'chats' => MessengerModel::getMyChats())
        );
    }


    public function new()
    {
        $this->View->render('messenger/new', array(
            'users' => MessengerModel::getAvailableUsersForNewChat()
        ));
    }

    public function start($partner_id)
    {
        MessengerModel::getOrCreateDirectChat(Session::get('user_id'), $partner_id);
        Redirect::to('messenger/chat/' . $partner_id);
    }

    public function chat($partner_id)
    {
        $chat_id = MessengerModel::getOrCreateDirectChat(Session::get('user_id'), $partner_id);

        MessengerModel::markChatAsRead($chat_id, Session::get('user_id'));

        $this->View->render('messenger/chat', array(
            'chats' => MessengerModel::getMyChats(),
            'my_groups' => GroupModel::getMyGroupChats(),
            'messages' => MessengerModel::getMessagesByChatId($chat_id, Session::get('user_id')),
            'active_chat_id' => $chat_id,
            'partner' => MessengerModel::getPartnerData($partner_id)
        ));
    }

    public function send()
    {
        MessengerModel::sendMessageToPartner(
            Session::get('user_id'),
            Request::post('partner_id'),
            Request::post('content', true)
        );

        Redirect::to('messenger/chat/' . Request::post('partner_id'));
    }

    public function sendTest($partner_id)
    {
        MessengerModel::sendMessageToPartner(
            Session::get('user_id'),
            $partner_id,
            Request::get('text')
        );

        Redirect::to('messenger/chat/' . $partner_id);
    }
}
