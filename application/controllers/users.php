<?php
class Users extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    public function logout()
    {
        $userid = $this->session->userdata('ACCOUNT');
        $this->session->sess_destroy();
        header('Location: '.base_url().'index.php/pages/view/login');
    }
    public function resetWithHash()
    {

        $hash = $this->input->post('hash');
        $account = $this->input->post('account');
        $password = $this->input->post('password');
        $query = $this->db->get_where('user', array('account'=>$account));
        $result = $query->result_array();
        $status = 'success';
        $data = '';
        if (sizeof($result) === 0) {
            $status = 'fail';
            $data = '帳號或者hash錯誤';
        } elseif ($password === '') {
            $status = 'fail';
            $data = '密碼不可為空';
        } elseif ((!$password) || (!$account) || (!$hash)) {
            $status = 'fail';
            $data = '資料不可為空';
        } else {
            $toks = explode(":", $result[0]["password"]);
            $h = $toks[2];
            if ($h === $hash) {
                $this->load->helper('hashsalt');
                $dbresult = $this->db->update(
                    'user',
                    array('password'=>create_hash($password)),
                    array('account'=>$account)
                );
                if (!$dbresult) {
                    $status = 'fail';
                    $data = '資料庫錯誤';
                }
            } else {
                $status = 'fail';
                $data = '帳號或者hash錯誤';
            }
        }
        header("Content-type: application/json");
        echo json_encode(array('status'=>$status, 'data'=>$data));
    }
    public function login()
    {
        $this->load->helper('hashsalt');
        $msg;
        $userid;
        $hashinfo;
        $this->session->unset_userdata('ACCOUNT');
        $account = $this->input->post("account");
        $password = $this->input->post("password");
		$query = $this->db->get_where(
            'user',
            array(
                'account'=>$account
            )
        );
        $result = $query->result_array();
        if (sizeof($result) == 0) {
            $msg['status'] = 'error';
            $msg['type'] = 'Account or password error';
        } else {
            $userid = $result[0]["account"];
            $hashinfo = $result[0]["password"];
        }
        if (isset($userid)&&(validate_password($password, $hashinfo))) {
            $session_data = array('ACCOUNT' => "$userid");
            $this->session->set_userdata($session_data);
        }
        if ($this->session->userdata('ACCOUNT')) {
            header(
                'Location: '. base_url(). 'index.php/pages/view/integration'
            );
        } else {
            header('Location: ' . base_url(). 'index.php/pages/view/login');
        }
    }
    public function register()
    {
		$this->load->helper('hashsalt');
        $account = $this->input->post('account');
        $password = $this->input->post('password');
        $confirm = $this->input->post('confirm');
		//check if the account is already in use
        $query = $this->db->query(
            "SELECT * FROM `user` WHERE  `account` = '$account'"
        );
        $result = $query->result_array();
        $response = array('data'=>'','status'=>'success');
        if (sizeof($result)!=0) {
            $response['data'] = '已經有人使用'.$account.'這個帳號名稱.';
            $response['status'] = 'error';
        }
		//check if the password matches confirm
        if ($password!=$confirm) {
            $response['data'] .= '密碼與確認密碼不符.';
            $response['status'] = 'error';
        }
        if ($password==''||$confirm=='') {
            $response['data'] .= '密碼/確認密碼不可為空.';
            $response['status'] = 'error';
        }
        if ($account=='') {
            $response['data'] .= '帳號不可為空.';
            $response['status'] = 'error';
        }
        if ($response['status']==='error') {
            header("Content-type: application/json");
            echo json_encode($response);
            return;
        }
		$password = create_hash($password);
        
		$this->db->insert('user', array('account'=>$account, 'password'=>$password));
        $query = $this->db->query(
            "SELECT * FROM `user` WHERE  `account` = '$account'"
        );
		
        $result = $query->result_array();
		if (sizeof($result)==0) {
            $response['data'] .= '寫入資料庫失敗，請重試\n';
            $response['status'] = 'error';
        }
        if ($response['status']!='success') {
            $response['data'] .= '帳號創造成功，請輸入帳號密碼';
            $response['status'] = 'success';
        }
		//initialize user setting infos
		$this->load->model('settingmodel', 'settingModel');
		$this->settingModel->initializeSettingsForUser($account);
		//end of init
        header("Content-type: application/json");
        echo json_encode($response);
		
    }
    public function forgotpw()
    {
        $account = $this->input->post('account');
        $response;
        $query = $this->db->query(
            "SELECT * FROM `user` WHERE  `account` = '$account'"
        );
        $result = $query->result_array();
        if (sizeof($result)!=0) {
            $toks = explode(":", $result[0]['password']);
            $password = $toks[2];
            $email = $account;
            $msg = '你的帳號為： '.$account.PHP_EOL
                .'你的Hash為： '.$password;
            $this->load->library('email');
            $this->email->set_newline("\r\n");
            // Set to, from, message, etc.
            $this->email->from(
                'storageintegration.sendmail@gmail.com',
                '雲端硬碟整合系統'
            );
            $this->email->to($email);
            $this->email->subject('帳號資料');
            $this->email->message($msg);
            $result = $this->email->send();
            $response['status'] = 'success';
            $response['data'] = '已寄信到您的信箱'.$email;
        } else {
            $response['status'] = 'error';
            $response['data'] = '查無此帳號';
        }
        header("Content-type: application/json");
        echo json_encode($response);
    }
}
