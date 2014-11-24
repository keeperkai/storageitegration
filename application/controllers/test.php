<?php
class Test extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('filemodel', 'fileModel');
        $this->load->model('storageaccountmodel', 'storageAccountModel');
    }
    public function echoPostBody(){
        $body = file_get_contents('php://input');
        header('Content-Type: text/plain');
        var_dump($_FILES['file']);
        echo $body;
    }
    public function justEchoOK(){
        header('Content-Type: text/plain');
        echo 'OK!'.PHP_EOL;
    }
}