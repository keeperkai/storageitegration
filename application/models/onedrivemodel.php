<?php
class OneDriveModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }
	private function refreshAccessToken(&$storage_account){
		$refresh_token = $storage_account['token'];
		$onedrive_client_id = '0000000048127A68';
		$onedrive_client_secret = 'MTuhJU2dIGjqndPvzc8PZwKztnuLQ6d6';
		$onedrive_redirect_uri = 'https://storageintegration.twbbs.org/index.php/ciauthorization/onedrivecode';
		$body = array(
				'client_id'=>$onedrive_client_id,
				'redirect_uri'=>$onedrive_redirect_uri,
				'client_secret'=>$onedrive_client_secret,
				'refresh_token'=>$refresh_token,
				'grant_type'=>'refresh_token'
		);
		$opt = array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => http_build_query($body),
				'protocol_version'=> 1.1,
				"follow_location"=>0
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen('https://login.live.com/oauth20_token.srf', 'r', false, $context);
		//var_dump($http_response_header);
		$response = stream_get_contents($fp);
		fclose($fp);
		$resp_obj = json_decode($response, true);
		$access_token = $resp_obj['access_token'];
		$refresh_token = $resp_obj['refresh_token'];
		$duration = $resp_obj['expires_in'];
		//update the columns in database
		$expire_date = new DateTime("now");
		$expire_date->add(new DateInterval('PT'.$duration.'S'));
		$this->db->update('storage_account', array('token'=>$refresh_token,'current_token'=>$access_token,'current_token_expire'=>$expire_date->format('Y-m-d H:i:s')),array('storage_account_id'=>$storage_account['storage_account_id']));
		//update the account info
		$storage_account['token'] = $refresh_token;
		$storage_account['current_token'] = $access_token;
		$storage_account['current_token_expire'] = $expire_date;
		return $resp_obj;
	}
	public function getAccessToken($storage_account){
		$expire_date = new DateTime($storage_account['current_token_expire']);
		$now = new DateTime("now");
		$access_token = $storage_account['current_token'];
		//var_dump($now);
		//var_dump($expire_date);
		if(($storage_account['current_token']=='')||$expire_date<$now){
			//refresh the token
			$obj = $this->refreshAccessToken($storage_account);
			$access_token = $obj['access_token'];
		}
		return $access_token;
	}
    public function getAccountQuotaInfoAsyncRequest($storage_account){
        $access_token = $this->getAccessToken($storage_account);
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => 'https://apis.live.net/v5.0/me/skydrive/quota?access_token='.$access_token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>array(
                'Connection: close'
            )
        );
        curl_setopt_array($ch, $curlConfig);
        return $ch;
    }
    public function getAccountQuotaInfoAsyncYield($result){
        $result = json_decode($result, true);
		$total = $result['quota'];
		$free = $result['available'];
		$used = $total-$free;
		$output = array('total'=>$total, 'used'=>$used, 'free'=>$free);
		return $output;
    }
	public function getAccountQuotaInfo($storage_account){
		//gets the quota info of an account
		//output: array(
		//'free': the free quota left in bytes
		//'used': the used quota in bytes
		//'total': total quota for this account
		$access_token = $this->getAccessToken($storage_account);
		$opt = array(
			'http' => array(
				'method' => 'GET',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen('https://apis.live.net/v5.0/me/skydrive/quota?access_token='.$access_token, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
		$resp_obj = json_decode($response, true);
		$total = $resp_obj['quota'];
		$free = $resp_obj['available'];
		$used = $total-$free;
		$output = array('total'=>$total, 'used'=>$used, 'free'=>$free);
		return $output;
	}
	public function getAccessTokenForClient($storage_account_data){
		return $this->getAccessToken($storage_account_data);
	}
	public function deleteStorageFile($storage_id, $storage_account){
		//DELETE https://apis.live.net/v5.0/file.b7c3b8f9g3616f6f.B7CB8F9G3626F6!225?access_token=ACCESS_TOKEN
		$access_token = $this->getAccessToken($storage_account);
		$delete_url = 'https://apis.live.net/v5.0/'.$storage_id.'?access_token='.$access_token;

		$opt = array(
			'http' => array(
				'method' => 'DELETE',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen($delete_url, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
		//$resp_obj = json_decode($response, true);

	}
    public function getEditLink($storage_account, $storage_id){
    //GET https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!126/shared_edit_link?access_token=ACCESS_TOKEN
        $access_token = $this->getAccessToken($storage_account);
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => "https://apis.live.net/v5.0/".$storage_id."/shared_edit_link?access_token=".$access_token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>array(
                'Connection: close'
            )
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        return $result['link'];
    }
    public function getPreviewLink($storage_account, $storage_id){
        /*
        //we don't do this now, because it revokes the edit token, now we use the embed link and parse the src of the embed iframe to gain a preview url, which doesn't interfere
        //with the edit link
        //GET https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!126/shared_read_link?access_token=ACCESS_TOKEN
        $access_token = $this->getAccessToken($storage_account);
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => "https://apis.live.net/v5.0/".$storage_id."/shared_read_link?access_token=".$access_token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>array(
                'Connection: close'
            )
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        
        return $result['link'];
        */
        $iframe_html = $this->getEmbedData($storage_account, $storage_id);
        //parse the src attribute of the iframe html
        $src_offset = strpos($iframe_html, 'src');
        $link_start_offset = strpos($iframe_html, '"', $src_offset)+1;
        $link_end_offset = strpos($iframe_html, '"', $link_start_offset)-1;
        $link_length = $link_end_offset - $link_start_offset + 1;
        return substr($iframe_html, $link_start_offset, $link_length);
    }
    public function getEmbedData($storage_account, $storage_id){
        $access_token = $this->getAccessToken($storage_account);
        $ch = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => "https://apis.live.net/v5.0/".$storage_id."/embed?access_token=".$access_token,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER =>array(
                'Connection: close'
            )
        );
        curl_setopt_array($ch, $curlConfig);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        return $result['embed_html'];
    }
    private function getRootId($storage_account){
        $access_token = $this->getAccessToken($storage_account);
        $root_data_url = 'https://apis.live.net/v5.0/me/skydrive?access_token='.$access_token;
        $opt = array(
			'http' => array(
				'method' => 'GET',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen($root_data_url, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
        $response = json_decode($response, true);
        $root_id = $response['id'];
        return $root_id;
    }
    /*
        returns an array of file_ids on onedrive storage account, under this folder.
        output format
    */
    private function getFilesUnderFolder($folder_id, $storage_account){
        $access_token = $this->getAccessToken($storage_account);
        $output = array();
        $url = 'https://apis.live.net/v5.0/'.$folder_id.'/files?access_token='.$access_token;
        $opt = array(
			'http' => array(
				'method' => 'GET',
				'protocol_version'=> 1.1,
				"follow_location"=>0,
				'header'           => [
					'Connection: close',
				],
			)
		);
		$context = stream_context_create($opt);
		$fp = fopen($url, 'r', false, $context);
		$response = stream_get_contents($fp);
		fclose($fp);
        $response = json_decode($response, true);
        /*
        response format
        {
            "data":
            [
                {
                    "id": "folder.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!110",
                    ...
                    "upload_location": "https://apis.live.net/v5.0/folder.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!110/files/"
                    ...
                    "type": "folder",
                    ...
                }, {
                    "id": "photo.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!131",
                    ...
                    "upload_location": "https://apis.live.net/v5.0/photo.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!131/content/",
                    ...
                    "type": "photo",
                    ...
                }, {
                    "id": "file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!119",
                    ...
                    "upload_location": "https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!119/content/",
                    ...
                    "type": "file",
                    ...
                }
            ]
        }
        */
        foreach($response['data'] as $file){
            $output[]=$file['id'];
        }
        return $output;
    }
    public function purge($storage_account){
        //get all the file/folders under root
        $access_token = $this->getAccessToken($storage_account);
        $root_id = $this->getRootId($storage_account);
        $files_under_root = $this->getFilesUnderFolder($root_id, $storage_account);
        //delete all files under root
        foreach($files_under_root as $file_id){
            $this->deleteStorageFile($file_id, $storage_account);
        }
    }
    //Shuffle related / upload/download/copy files------------
    /*
        creates a folder under the root folder
        
        returns:
        the upload_location of the created folder
    */
    private function createFolder($storage_account, $name){
        //POST https://apis.live.net/v5.0/me/skydrive
        //Authorization: Bearer ACCESS_TOKEN
        //Content-Type: application/json
        //{
        //    "name": "My example folder"
        //}
        $data = array("name" => $name);
        $data_string = json_encode($data);

        $ch = curl_init('https://apis.live.net/v5.0/me/skydrive?overwrite=ChooseNewName');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer '.$this->getAccessToken($storage_account),
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
            'Connection: close'
            )
            
        );
        /*
            result format:
            {
                "id": "folder.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!110", 
                ...
                "upload_location": "https://apis.live.net/v5.0/folder.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!110/files"
                ...
                "type": "folder",
                ...
            }
        */
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        return $result['upload_location'];
    }
    public function uploadFile($storage_account, $file_name, $file_mime, $file_size, $file){
        //we can use the put upload directly, however we should still add a directory first, because we want the file name not to be changed.
        $upload_url = $this->createFolder($storage_account, (new DateTime("now"))->format('Y-m-d_H_i_s'));
        //upload put request
        //PUT https://apis.live.net/v5.0/me/skydrive/files/HelloWorld.txt?access_token=ACCESS_TOKEN
        //
        //Hello, World!
        $ch = curl_init();
        /*
        echo 'uploading file for onedrive : '.$file_name.'    ';
        echo 'file_size: '.$file_size.'    ';
        fseek($file, 0, SEEK_END);
        echo 'file resource size: '.ftell($file).'  ';//165 wtf?
        if(ftell($file)< 200){
            rewind($file);
            echo '  content of file resource: '.stream_get_contents($file).'    ';
        }
        rewind($file);
        */
        curl_setopt($ch, CURLOPT_URL, $upload_url.$file_name.'?access_token='.$this->getAccessToken($storage_account));
        curl_setopt($ch, CURLOPT_PUT, true);
        curl_setopt($ch, CURLOPT_INFILE, $file);
        curl_setopt($ch, CURLOPT_INFILESIZE, $file_size);//if this causes a block, it might be because there was an error for the previous call to onedrive api for $file
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Connection: close',
            'Content-Length: ' . $file_size
            )
        );
        
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        rewind($file);
        return $result['id'];
    }
    /*
        download a storage file on the server and stream it to hard drive
        storage_id: the id of the file to download on the storage provider
        file: the files resource to write to, the function will start writing from the current position, so you need to initialize it to the
        right position. By doing it this way, you can download a lot of data from different storage files and append them into the same file
        resource.

        Take note that curl rewinds the file pointer after it has finished the operation. If you want to append multiple downloads together, use
        fseek($fp, 0, SEEK_END); to move the position to the current end before calling this function.

        returns:
        the handle to the file resource that has been downloaded, with the position set back to 0
    */
    public function downloadFile($storage_account, $storage_id, $file){
        //GET https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!126/content?access_token=ACCESS_TOKEN&download=true
        $dl_link = 'https://apis.live.net/v5.0/'.$storage_id.'/content?access_token='.$this->getAccessToken($storage_account).'&download=true';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dl_link);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Connection: close'
            )
        );
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        rewind($file);
        return $result['id'];
        
    }
    /*
        this function downloads a part of a storage file to our server, the byte offsets are specified by $start_offset and $end_offset(inclusive),
        the function writes to the $file resource and returns a reference to it.
    */
    public function downloadChunk($storage_account, $storage_id, $start_offset, $end_offset, $file){
        $dl_link = 'https://apis.live.net/v5.0/'.$storage_id.'/content?access_token='.$this->getAccessToken($storage_account).'&download=true';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $dl_link);
        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Connection: close',
                'Range: bytes='.$start_offset.'-'.$end_offset
            )
        );
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        rewind($file);
        return $result['id'];
    }
    public function getDownloadLink($storage_id, $owner_account, $user){
        //GET https://apis.live.net/v5.0/file.a6b2a7e8f2515e5e.A6B2A7E8F2515E5E!126/content?suppress_redirects=true?access_token=ACCESS_TOKEN
        $access_token = $this->getAccessToken($owner_account);
        $ch = curl_init('https://apis.live.net/v5.0/'.$storage_id.'/content?suppress_redirects=true&access_token='.$access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $result = json_decode($result, true);
        curl_close($ch);
        //var_dump($result);
        /*
            array(
                'location'=> url to download
            )
        */
        return array(
            'status'=>'success',
            'link'=>$result['location'].'&download=true'//perhaps we won't add download=true for the preview version... it looks better than the embed link we use now
        );
    }
    //create document related
    public function createDocument($storage_account, $name){
        $doc_path = $this->config->item('document_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('document_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
    public function createSpreadSheet($storage_account, $name){
        $doc_path = $this->config->item('spreadsheet_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('spreadsheet_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
    public function createPresentation($storage_account, $name){
        $doc_path = $this->config->item('presentation_file_path');
        $file = fopen($doc_path, 'r');
        $file_size = filesize($doc_path);
        $file_mime = $this->config->item('presentation_file_mime');
        $id = $this->uploadFile($storage_account, $name, $file_mime, $file_size, $file, true);
        fclose($file);
        return $id;
    }
}