<?php

class FileModel extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
		$this->load->helper('googledrive');
		$this->load->model('settingmodel', 'settingModel');
		$this->load->model('storageaccountmodel', 'storageAccountModel');
		$this->load->model('googledrivemodel', 'googleDriveModel');
		$this->load->model('cloudstoragemodel', 'cloudStorageModel');
    }
    /*
		1.delete the storage file on the cloud storage
		2.delete the storage file data in our system
	*/
	public function deleteStorageFile($storage_file_id){
		$sfile = $this->getStorageFile($storage_file_id);
		$acc = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
		//delete storage file from cloud storage
		$this->cloudStorageModel->deleteStorageFile($sfile['storage_id'], $acc);
		//delete storage file meta data in our db
		$this->deleteStorageFileMetaDataFromDatabase($storage_file_id);
	}
	public function deleteStorageFileMetaDataFromDatabase($storage_file_id){
		$this->db->delete('storage_file', array('storage_file_id'=>$storage_file_id));
	}
	/*
		1.register a new storage file for a virtual file.
		2.set the permissions of the storage file on the cloud storage if needed(ie. googledrive storage files)
		
		input of $storage_file_data should be same as the fields in the database
	*/
	public function registerStorageFileAndSetPermissionsOnStorage($storage_file_data){
		$this->registerStorageFile($storage_file_data);
		//propagate the permissons to the cloud storage
		$this->propagateStorageFilePermissionToCloudStorage($storage_file_data);
	}
	//google section
	/*
	private function getPermissionIdsForGoogle($user){
		$q = $this->db->get_where('storage_account', array('account'=>$user, 'token_type'=>'googledrive'));
		$r = $q->result_array();
		if(sizeof($r)>0){
			$output = array();
			foreach($r as $storageaccount){
				$output[]=$storageaccount['permission_id'];
			}
			return $output;
		}
		return false;
	}
	private function addPermissionForGoogleFile($sfile, $user, $role){
		//sfile is a join of storage_file and storage_account, so it contains all the necessary data for this single storage file.
		//adds the permissions to the storage files on gdrive
		//Todo, save access token after refresh, so we don't need to refresh tokens every time we want to do something
		//setup the client and get the list of permissions on gdrive for the file
		$service = setupGoogleDriveService($sfile['token']);
		$permissions = $service->permissions->listPermissions($sfile['storage_id']);
		$permits = $permissions->getItems();
		//find the user's google drive permission id;
		$permission_id_list = $this->getPermissionIdsForGoogle($user);
		//see if the file already has a permission for that user on google
		foreach($permission_id_list as $uid){
			$existing_perm = false;
			foreach($permits as $fperm){
				if($fperm['id']==$uid){
					$existing_perm = $fperm;
					break;
				}
			}
			//update existing perm or insert a new one
			if($existing_perm){//update
				if($this->roleCompare($role, $existing_perm['role'])>0){
					$permission = $service->permissions->get($sfile['storage_id'], $uid);
					$permission->setRole($role);
					$service->permissions->update($sfile['storage_id'], $uid, $permission);
				}
			}else{//insert new one
				$newPermission = new Google_Permission();
				$newPermission->setId($uid);
				$newPermission->setType('user');
				$newPermission->setRole($role);
				$service->permissions->insert($sfile['storage_id'], $newPermission);
			}
		}
		
		
	}
	*/
	//--------------end of google section
	public function getStorageFilesForVirtualFileId($virtual_file_id){
		$q = $this->db->get_where('storage_file', array('virtual_file_id'=>$virtual_file_id));
		$sfiles = $q->result_array();
		return $sfiles;
	}
    public function getStorageFilesForVirtualFileIdSorted($virtual_file_id, $fieldname, $ascend){
        $asc_or_desc = 'desc';
        if($ascend) $asc_or_desc = 'asc';
        $this->db->order_by($fieldname, $asc_or_desc); 
        return $this->getStorageFilesForVirtualFileId($virtual_file_id);
    }
	/*
		gets a storage file data in db, also joins the virtual file data
	*/
	public function getStorageFile($storage_file_id){
		$this->db->select('*');
		$this->db->from('storage_file');
		$this->db->join('virtual_file', 'virtual_file.virtual_file_id = storage_file.virtual_file_id', 'left');
		$this->db->where(array('storage_file.storage_file_id'=>$storage_file_id));
		$q = $this->db->get();
		$r = $q->result_array();
		if(sizeof($r)==1){
			return $r[0];
		}else return false;
	}
	
	public function getAllMovableStorageFilesFromStorageAccount($user, $storage_account_id){
		//$q = $this->db->get_where('storage_file', array('storage_account_id'=>$storage_account_id));
		//$storage_files = $q->result_array();
		
		$this->db->select('*');
		$this->db->from('storage_file');
		$this->db->join('virtual_file', 'virtual_file.virtual_file_id = storage_file.virtual_file_id', 'left');
		$this->db->where(array('storage_file.storage_account_id'=>$storage_account_id, 'virtual_file.allow_chunk'=>true));
		$q = $this->db->get();
		$storage_files = $q->result_array();
		return $storage_files;
	}
	
    
    public function attachLooseFilesToOwnerTree($vfiles){
        foreach($vfiles as $loosefile){
            $user = $loosefile['account'];
            $loose_file_folder = $this->getLooseFilesFolderForUser($user);
            //var_dump($loose_file_folder);
            $this->db->update('virtual_file', array('parent_virtual_file_id'=>$loose_file_folder['virtual_file_id']),array('virtual_file_id'=>$loosefile['virtual_file_id']));
        }
    }
    public function createLooseFilesFolderForUser($user){
        //$this->db->insert('virtual_file', array('file_type'=>'folder', 'name'=>'Loose Files', 'account'=>$user));
        $folder_id = $this->makeFolder($user, 'Loose Files', -1);
        $q = $this->db->get_where('virtual_file', array('virtual_file_id'=>$folder_id));
        $r = $q->result_array();
        return $r[0];
    }
    public function getLooseFilesFolderForUser($user){//tries to find the loose files folder for the user, if none exist then create one
        $q = $this->db->get_where('virtual_file', array('file_type'=>'folder', 'name'=>'Loose Files', 'account'=>$user));
        $r = $q->result_array();
        if(sizeof($r)>0){
            return $r[0];
        }else{
            return $this->createLooseFilesFolderForUser($user);
        }
    }
    /*
        returns the array of virtual file entries of a root folder
    */
	public function getChildrenVirtualFiles($rootdir_file_id){
        $q = $this->db->get_where('virtual_file', array('parent_virtual_file_id'=>$rootdir_file_id));
        $r = $q->result_array();
        return $r;
    }
    public function moveVirtualFile($virtual_file_id, $new_parent_virtual_file_id, $inherit_parent_permissions = true){
        $this->db->update(
            'virtual_file',
            array('parent_virtual_file_id'=>$new_parent_virtual_file_id),
            array('virtual_file_id'=>$virtual_file_id)
        );
        if($inherit_parent_permissions){
            $this->inheritParentPermission($virtual_file_id);
            //add permissions on cloud storage
            $this->propagateVirtualFilePermissionToCloudStorageWithId($virtual_file_id);
        }
        return true;
    }
    /*
        output format:
        array(
            'owner'=>owner account,
            'writers'=>array(
                account1,
                account2,
                ...
            )
            'readers'=>array(
                reader account1,
                reader account2,
                ...
            )
        )
    */
    public function getPermissionsForVirtualFileStructured($virtual_file_id){
        $permissions = $this->getPermissionsForVirtualFile($virtual_file_id);
        $output = array('owner'=>'', 'writer'=>array(), 'reader'=>array());
        foreach($permissions as $permit){
            $role = $permit['role'];
            $user = $permit['account'];
            if($role=='owner'){
                $output['owner'] = $user;
            }else if($role == 'writer'){
                $output['writer'][] = $user;
            }else if($role == 'reader'){
                $output['reader'][] = $user;
            }
        }
        return $output;
    }
	public function getPermissionsForVirtualFile($virtual_file_id){
		$q = $this->db->get_where('file_permissions', array('virtual_file_id'=>$virtual_file_id));
		$r = $q->result_array();
		return $r;
	}
    public function getVirtualFilePermissionForUser($virtual_file_id, $user){
        $q = $this->db->get_where('file_permissions', array('virtual_file_id'=>$virtual_file_id, 'account'=>$user));
		$r = $q->result_array();
        if(sizeof($r)>0) return $r[0];
        else return false;
    }
    public function getUserRoleForVirtualFile($virtual_file_id, $user){
        $permit = $this->getVirtualFilePermissionForUser($virtual_file_id, $user);
        if(!$permit){
            return false;
        }else{
            return $permit['role'];
        }
    }
	public function getVirtualFileData($virtual_file_id){
		$q = $this->db->get_where('virtual_file', array('virtual_file_id'=>$virtual_file_id));
		$r = $q->result_array();
		if(sizeof($r)>0){
			return $r[0];
		}else{
			return false;
		}
	}
	private function getRoleVal($a){
		if($a=='reader') return 0;
		else if($a=='writer') return 1;
		else if($a=='owner') return 2;
		else return -1;
	}
	public function roleCompare($a, $b){
		$aval = $this->getRoleVal($a);
		$bval = $this->getRoleVal($b);
		if($aval == $bval) return 0;
		else if($aval > $bval) return 1;
		else return -1;
	}
	/*
		propagate the permission settings on our system to cloud storage provider for this storage file
		it takes the storage file id as input
	*/
	public function propagateStorageFilePermissionToCloudStorageWithId($storage_file_id){
		$sfile = $this->getStorageFile($storage_file_id);
		$this->propagateStorageFilePermissionToCloudStorage($sfile);
	}
	/*
		same as above, except it takes the data of the storage file data as input,
		this function could be used for storage files that have not been saved to the database
		yet, it does not need the storage_file_id
        Note that this function only adds permissions, it doesn't delete permissions that are absent.
	*/
	public function propagateStorageFilePermissionToCloudStorage($sfile){
		//get the storage account
		$acc = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
		//for all types of accounts that need to propagate permissions beforehand, do so here
		
		//get all the users that have any form of permission to the file
		$permits = $this->getPermissionsForVirtualFile($sfile['virtual_file_id']);
		foreach($permits as $perm){
			//$this->cloudStorageModel->addPermissionForUser($sfile['storage_id'], $acc, $perm['account'], $perm['role']);
            $this->cloudStorageModel->setPermissionForUser($sfile['storage_id'], $acc, $perm['account'], $perm['role']);
		}
	}
    public function propagateVirtualFilePermissionToCloudStorageWithId($virtual_file_id){
        $vfile = $this->getVirtualFileData($virtual_file_id);
        if($vfile){
            return $this->propagateVirtualFilePermissionToCloudStorage($vfile);
        }
        return false;
    }
    public function propagateVirtualFilePermissionToCloudStorage($vfile){
        if($vfile['file_type'] == 'folder') return true;
        $permits = $this->getPermissionsForVirtualFile($vfile['virtual_file_id']);
        $sfiles = $this->getStorageFilesForVirtualFileId($vfile['virtual_file_id']);
		foreach($permits as $perm){
            foreach($sfiles as $sfile){
                $acc = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
                $this->cloudStorageModel->setPermissionForUser($sfile['storage_id'], $acc, $perm['account'], $perm['role']);
            }
		}
        return true;
    }
	public function inheritParentPermission($virtual_file_id){
        $vfile = $this->getVirtualFileData($virtual_file_id);
		$parent_id = $vfile['parent_virtual_file_id'];
		if($parent_id == -1) return;
		$file_permissions = $this->getPermissionsForVirtualFile($virtual_file_id);
		$parent_permissions = $this->getPermissionsForVirtualFile($parent_id);
		foreach($parent_permissions as $permit){
			if($permit['role'] != 'owner'){
				$this->addPermission($virtual_file_id, $permit['account'], $permit['role']);
			}else{
				$this->addPermission($virtual_file_id, $permit['account'], 'writer');
			}
		}
	}
    /*
        this function adds a permission to a virtual file
    */
	public function addPermission($virtual_file_id, $user, $role){
		$vfile = $this->getVirtualFileData($virtual_file_id);
		if($vfile){
			$type = $vfile['file_type'];
			//add permission for the file
			$this->addPermissionForSingleFile($virtual_file_id, $user, $role);
			if($type == 'folder'){//recurse
				$children = $this->getChildrenVirtualFiles($virtual_file_id);
				foreach($children as $child){
					$this->addPermission($child['virtual_file_id'], $user, $role);
				}
			}
		}
	}
    /*
        add's permission in our system to a file, does not add the permission to the cloud storages. for that, use cloudstoragemodel->propagate,
        it is an incrementing permission, meaning if the permission in the database is smaller than the permission that we want to set,
        we will update the database and give the user the current role we want to set instead of inserting a new one(which would be a bug)
        
    */
	public function addPermissionForSingleFile($virtual_file_id, $user, $role){
		//first check if the user already has permission
		$q = $this->db->get_where('file_permissions', array('account'=>$user, 'virtual_file_id'=>$virtual_file_id));
		$r = $q->result_array();
		if(sizeof($r)>0){//user already has permission, change the permission if the current permission < set permission
			$permit = $r[0];
			$comp = $this->roleCompare($role, $permit['role']);
			//if we want to set this user as owner, we need to remove the other owner
			if($role=='owner'){
				$this->db->where('account !=', $user);
				$this->db->where('virtual_file_id', $virtual_file_id);
				$this->db->where('$role', 'owner');
				$this->db->update('file_permissions', array('role'=>'writer'));
			}
			
			if($comp>0){
				$this->db->update('file_permissions', array('role'=>$role), array('account'=>$user, 'virtual_file_id'=>$virtual_file_id));
			}
			
		}else{//user doesn't have permission for this file, insert it.
			$this->db->insert('file_permissions', array('account'=>$user, 'virtual_file_id'=>$virtual_file_id, 'role'=>$role));
		}
        //we don't do this in this function anymore, we use the propogate function or the set permission functons in the cloudstoragemodel
		//if the file is hosted on google, actually add the file permission
		//just find all storage files linked to this virtual file and for the google ones, propagate the permission
		/*
        $this->db->select('*');
		$this->db->from('storage_file');
		$this->db->join('storage_account', 'storage_account.storage_account_id = storage_file.storage_account_id', 'left');
		$this->db->where(array('storage_file.virtual_file_id'=>$virtual_file_id));
		$q = $this->db->get();
		$r = $q->result_array();
		
		foreach($r as $sfile){
			if($sfile['token_type']=='googledrive'){
				//$this->addPermissionForGoogleFile($sfile, $user, $role);
				$this->googleDriveModel->addPermissionForUser($sfile['storage_id'], $sfile['storage_account_id'], $user, $role);
			}
		}
        */
	}
    /*
        this function changes a permission to a virtual file, different from add permission
        (i)it will only change the permission if it exists.
        (ii)it will downgrade the permission, not only upgrade.
        
        Take note that we should not downgrade the owner of the file
    */
	public function changePermission($virtual_file_id, $user, $role){
		$vfile = $this->getVirtualFileData($virtual_file_id);
		if($vfile){
			$type = $vfile['file_type'];
			//add permission for the file
			$this->changePermissionForSingleFile($virtual_file_id, $user, $role);
			if($type == 'folder'){//recurse
				$children = $this->getChildrenVirtualFiles($virtual_file_id);
				foreach($children as $child){
					$this->changePermission($child['virtual_file_id'], $user, $role);
				}
			}
		}
	}
    /*
        change permission for single file, if the permission doesn't exist for this user, nothing will be done
        take note that the owner cannot be changed
    */
    public function changePermissionForSingleFile($virtual_file_id, $user, $role){
        //first check if the user is the owner
        $permit = $this->getVirtualFilePermissionForUser($virtual_file_id, $user);
        if($permit){
            if($permit['role'] == 'owner') return false;
            else{//update the permission
                return $this->db->update('file_permissions', array('role'=>$role), array('virtual_file_id'=>$virtual_file_id, 'account'=>$user));
            }
        }
        //else, user has no access to the file, so don't change anything
        return false;
    }
    /*
        does not delete the owner
    */
    public function deletePermission($virtual_file_id, $user){
        $vfile = $this->getVirtualFileData($virtual_file_id);
		if($vfile){
			$type = $vfile['file_type'];
			$this->deletePermissionForSingleFile($virtual_file_id, $user);
			if($type == 'folder'){//recurse
				$children = $this->getChildrenVirtualFiles($virtual_file_id);
				foreach($children as $child){
					$this->deletePermission($child['virtual_file_id'], $user);
				}
			}
		}
    }
    /*
        does not delete the owner
    */
    public function deletePermissionForSingleFile($virtual_file_id, $user){
        //first check if the user is the owner
        $permit = $this->getVirtualFilePermissionForUser($virtual_file_id, $user);
        if($permit){
            if($permit['role'] == 'owner') return false;
            else{//delete the permission
                return $this->db->delete('file_permissions', array('virtual_file_id'=>$virtual_file_id, 'account'=>$user));
            }
        }
        //else, user has no access to the file, so don't change anything
        return false;
    }
    /*
        modifies the permissions of a virtual file according to share change
        format of share change:
        {
            updated: true or false
            permission_change:
            {
              user_account: writer or reader,
              user_account2: ...
            }
            permission_delete:
            [
              user_account,
              useraccount2,
              ..
            ]
            permission_insert:
            {
              user_account: writer or reader
            }
        }
    */
    public function modifyShareInfo($virtual_file_id, $share_change){
        //add the inserted permissions
        foreach($share_change['permission_insert'] as $acc=>$role){
            $this->addPermission($virtual_file_id, $acc, $role);
        }
        foreach($share_change['permission_delete'] as $acc){
            $this->deletePermission($virtual_file_id, $acc);
        }
        foreach($share_change['permission_change'] as $acc=>$role){
            $this->changePermission($virtual_file_id, $acc, $role);
        }
        //propagate the permissions if necessary
        //get all storage files of the virtual file
        $sfiles_with_account_info = $this->getStorageDataForVirtualFile($virtual_file_id);
        foreach($sfiles_with_account_info as $sfile){
            $this->propagateStorageFilePermissionToCloudStorage($sfile);
            //delete permissions on storage
            $owner_acc = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
            foreach($share_change['permission_delete'] as $user){
                $this->cloudStorageModel->deletePermissionForUser($sfile['storage_id'], $owner_acc, $user);
            }
        }
        
    }
	public function registerStorageFile($storageFileData){
		return $this->db->insert('storage_file', $storageFileData);
	}
	public function registerStorageFileData($storage_file_data, $virtual_file_id){
	//adt:
	/*
	array(
		storage_account_id
		storage_file_type
		byte_offset_start
		byte_offset_end
		storage_id
	)
	*/
		//var_dump(is_array($storage_file_data));
		$output = false;
		foreach($storage_file_data as $storageData){
			$storageData['virtual_file_id'] = $virtual_file_id;
			$output = $this->registerStorageFile($storageData);
			if(!$output) return false;
		}
		return true;
	}
	public function findLatestVirtualFileWithProperties($fileData){
		$this->db->select('*');
		$this->db->where($fileData);
		$this->db->order_by('virtual_file_id', 'desc');
		$q = $this->db->get('virtual_file');
		$r = $q->result_array();
		if(sizeof($r)>0)	return $r[0];
		else return false;
	}
    /*
        register a file to the system, this includes adding to virtual_file table, storage_file table(if needed, folders don't need this),
        adding data to file_permissions table, setting the permissions on cloud storage(if needed, like storage files on googledrive)
        returns the virtual_file_id
    */
    public function registerFileToSystem($user, $file_type, $mime_type, $name, $extension, $parent_virtual_file_id, $storage_file_data){
        $fileData = array();
		$fileData['account'] = $user;
		$fileData['file_type'] = $file_type;
        $fileData['mime_type'] = $mime_type;
		$fileData['name'] = $name;
		$fileData['extension'] = $extension;
		$fileData['parent_virtual_file_id'] = $parent_virtual_file_id;
		
        $allow_chunk = false;
        if(sizeof($storage_file_data)>1)    $allow_chunk = true;//if the file is already chunked, it doesn't matter what extension it is
        else if($fileData['file_type']=='folder') $allow_chunk = false;
        else{
            $allow_chunk = $this->settingModel->chunkAllowedForExtension($user, $fileData['extension']);
        }
        
        $fileData['allow_chunk'] = $allow_chunk;
        
		/*
		structure for storage_file_data:
        array(
            array(
                storage_account_id
                storage_file_type
                byte_offset_start
                byte_offset_end
                storage_id
            )
        )
		*/
		$resp = true;
		$virtual_file_id = false;
		if($this->registerFile($fileData)){
			$insertedfile = $this->findLatestVirtualFileWithProperties($fileData);
			$virtual_file_id = $insertedfile['virtual_file_id'];
			//$storage_file_data['virtual_file_id'] = $virtual_file_id;
		
			//add permission for the owner
			$this->addPermission($virtual_file_id, $user, 'owner');
			//inherit permissions from parent
			$this->inheritParentPermission($virtual_file_id);
			if($fileData['file_type']!='folder'){
				//foreach storage file, register them and propagate the permissions to storage if needed
                foreach($storage_file_data as $sfile){
                   $sfile['virtual_file_id'] = $virtual_file_id;
                   $this->registerStorageFileAndSetPermissionsOnStorage($sfile);
                }
            }
            
		}
        return $virtual_file_id;
    }
    public function makeFolder($user, $name, $parent_virtual_file_id){
        $vfile_id = $this->registerFileToSystem($user, 'folder', '', $name, 'dir', $parent_virtual_file_id, array());
        return $vfile_id;
    }
	public function registerFile($fileData){
        return $this->db->insert('virtual_file',$fileData);
    }
    public function getVirtualFilesForUserWithAccessGreaterEqualThan($user, $role){
        $output = array();
        if($this->roleCompare($role,'owner')<=0){
            $output = array_merge($output, $this->getVirtualFilesAsArrayForUser($user, 'owner'));
        }
        
        if($this->roleCompare($role,'writer')<=0){
            $output = array_merge($output, $this->getVirtualFilesAsArrayForUser($user, 'writer'));
        }
        
        if($this->roleCompare($role,'reader')<=0){
            $output = array_merge($output, $this->getVirtualFilesAsArrayForUser($user, 'reader'));
        }
        return $output;
    }
	public function getVirtualFilesAsArrayForUser($user, $role){
        $this->db->select('*');
		$this->db->from('file_permissions');
		$this->db->join('virtual_file', 'file_permissions.virtual_file_id = virtual_file.virtual_file_id', 'left');
		$this->db->where(array('file_permissions.account'=>$user, 'file_permissions.role'=>$role));
		$q = $this->db->get();
		$r = $q->result_array();
        return $r;
	}
    /*
        returns an array of virtual files, this includes files that are shared with the user but not owned by the user.
        we do this by getting the permissions and joining data with the virtual file.
        the output will organize the data to be usable in the webpage by ztree, letting the id = virtual_file_id +3, note that in the client when we send
        requests back here to the server, we use the virtual_file_id in the node instead of the id or pId of the ztree node.
    */
	public function getVirtualFileTreeForUser($user){
		$this->db->select('*');
		$this->db->from('file_permissions');
		$this->db->join('virtual_file', 'file_permissions.virtual_file_id = virtual_file.virtual_file_id', 'left');
		$this->db->where(array('file_permissions.account'=>$user));
		$q = $this->db->get();
		$r = $q->result_array();
		$virtual_file_map = $this->constructVirtualFileMap($r);
		/*
		$virtual_file_tree = constructVirtualFileTree($virtual_file_map);
		return $virtual_file_tree;
		*/
		//if the parent file id is -1, then depending on the permission, the file belongs in mystorage or shared with memory_get_peak_usage
		//if the parent file id can be found in the set, leave it alone.
		//if the parent file id is not found in the set and it is not -1, then this folder is a child of shared with me.
		foreach($virtual_file_map as $id=>$file){
			$parent_id = $file['parent_virtual_file_id'];
			$virtual_file_map[$id]['id'] = $file['virtual_file_id'] + 3;
			$virtual_file_map[$id]['pId'] = $file['parent_virtual_file_id'] + 3;//will be overwritten if needed
			if(!array_key_exists($parent_id, $virtual_file_map)){
				if($parent_id == -1){
					if($file['role'] == 'owner'){
						$virtual_file_map[$id]['pId'] = 1;//mystorage
					}else{
						$virtual_file_map[$id]['pId'] = 2;//shared with me
					}
				}else{
					$virtual_file_map[$id]['pId'] = 2;
				}
			}
		}
		$virtual_file_map[-2] = array('id'=>1,'pId'=>0, 'virtual_file_id'=> -1, 'name'=>'My Storage','file_type'=>'folder');
		$virtual_file_map[-1] = array('id'=>2,'pId'=>0, 'virtual_file_id'=> -2, 'name'=>'Shared With Me','file_type'=>'folder');
		return array_values($virtual_file_map);
	}
	public function constructVirtualFileTree($file_map){
		$parent_id_grouping = array();
		foreach($file_map as $id=>$file){
			if(!array_key_exists($file['parent_virtual_file_id'], $parent_id_grouping)){
				$parent_id_grouping[$file['parent_virtual_file_id']] = array($file);
			}else{
				$parent_id_grouping[$file['parent_virtual_file_id']][] = $file;
			}
		}
		//construct the tree
		//add the parts that are connected to this users root
		$virtual_file_tree = array(array('id'=>1,'pId'=>0,'name'=>'MyStorage'),array('id'=>2,'pId'=>0,'name'=>'Shared With Me'));
		DFSPullChildren($file_map, $virtual_file_tree, $parent_id_grouping, -1);
	}
	public function hasAccess($user, $virtual_file_id, $role){
        if($virtual_file_id === -1) return true;
        $q = $this->db->get_where('file_permissions', array('virtual_file_id'=>$virtual_file_id, 'account'=>$user));
        $r = $q->result_array();
        if(sizeof($r)>0){
            $permit = $r[0];
            if($this->roleCompare($role, $permit['role'])<=0) return true;
        }
        return false;
        
    }
    public function getStorageDataForVirtualFile($virtual_file_id){
        $this->db->select('*');
        $this->db->from('storage_file');
        $this->db->join('storage_account', 'storage_file.storage_account_id= storage_account.storage_account_id', 'left');
        $this->db->where(array('storage_file.virtual_file_id'=>$virtual_file_id));
        $q = $this->db->get();
        $r = $q->result_array();
        return $r;
    }
    public function deleteStorageDataForVirtualFile($virtual_file_id){
        $sfiles = $this->getStorageDataForVirtualFile($virtual_file_id);
        foreach($sfiles as $sfile){
            $this->deleteStorageFile($sfile['storage_file_id']);
        }
    }
    public function deleteSingleFile($virtual_file_id){
        $vfile = $this->getVirtualFileData($virtual_file_id);
        if(!$vfile) return;
        $this->deleteStorageDataForVirtualFile($virtual_file_id);
        $this->db->delete('virtual_file', array('virtual_file_id'=>$virtual_file_id));
        $this->db->delete('file_permissions', array('virtual_file_id'=>$virtual_file_id));
        $this->db->delete('storage_file', array('virtual_file_id'=>$virtual_file_id));
    }
    function simpleDeleteVirtualFilePermissionForUser($virtual_file_id, $user){
        return $this->db->delete('file_permissions', array('virtual_file_id'=>$virtual_file_id, 'account'=>$user));
    }
    public function deleteFileWithUserContext($virtual_file_id, $user){
        $loosefiles = array();
        $vfile = $this->getVirtualFileData($virtual_file_id);
        $permission = $this->getVirtualFilePermissionForUser($virtual_file_id, $user);
        $role = 'none';
        if($permission) $role = $permission['role'];
        if(!$vfile) return;
        $filetype = $vfile['file_type'];
        if($filetype == 'folder'){
            $children = $this->getChildrenVirtualFiles($vfile['virtual_file_id']);
            if($role == 'owner'){
                //actually delete the folder and register children as a loose file if it is not owned by this user
                foreach($children as $child_vfile){
                    $child_permission = $this->getVirtualFilePermissionForUser($child_vfile['virtual_file_id'], $user);
                    if($child_permission){
                        if($child_permission['role']!='owner'){
                            $loosefiles[] = $child_vfile;
                        }
                    }else{//this user doesn't have the permission, this will also result in a loose file
                        $loosefiles[] = $child_vfile;
                    }
                }
                //actually delete
                $this->deleteSingleFile($virtual_file_id);
            }else if($role == 'none'){
                //do nothing
            }else{//reader or writer, simply just delete the permission of this user
                $this->simpleDeleteVirtualFilePermissionForUser($virtual_file_id, $user);
            }
            foreach($children as $child_vfile){
                $loosefilesofchild = $this->deleteFileWithUserContext($child_vfile['virtual_file_id'], $user);
                $loosefiles = array_merge($loosefiles, $loosefilesofchild);
            }
        }else{//not a folder, no children, just delete the file from our system, and the physical data on storages(owner). if the user isn't the owner just delete the permission
        //also delete the permission on the storages
            if($role == 'owner'){
                $this->deleteSingleFile($virtual_file_id);
            }else if($role == 'none'){
                //do nothing
            }else{
                //delete permission
                $this->simpleDeleteVirtualFilePermissionForUser($virtual_file_id, $user);
            }
        }
        return $loosefiles;
    }
    /*
        accepts an array of indexed virtual files,
        outputs a map using the 'virtual_file_id' as key, the set of virtual files that include all the other files in the input(
        the output files are also in the input array, it just eliminates the ones that are descendants of other files)
    */
    public function getIndependentFiles($file_collection){//returns independent files, i.e. if there are file ancestors->descendants, return a collection with redundant files removed
        //input should be an array of array, each lower array containing a associative key 'file_id'
        $output = $file_collection;
        $output = array_to_map('virtual_file_id', $output);
        $set = array_to_map('virtual_file_id', $file_collection);
        foreach($file_collection as $file){
            $ancestors = $this->getAncestorFileIds($file);
            $notindie = false;
            foreach($ancestors as $id => $whatever){
                if(array_key_exists($id, $set)){//key exists in the set, remove this file as it is not independent
                    $notindie = true;
                    break;
                }
            }
            if($notindie){
                unset($output[$file['virtual_file_id']]);
            }
        }
        return $output;
        
    }
    public function getDescendantFileIds($file, $includethisfile = true){
        $output = array();
        $current = $file;
        $root_id = $file['virtual_file_id'];
        if($includethisfile){
            $output[]=$file['virtual_file_id'];
        }
        $q = $this->db->get_where('virtual_file', array('parent_virtual_file_id'=>$file['virtual_file_id']));
        $r = $q->result_array();
        foreach($r as $child){
            $output = array_merge($output, $this->getDescendantFileIds($child, true));
        }
        return $output;
    }
    public function getAncestorFileIds($file){// outputs a map with fileIds as key and true as value, file is a virtual file
        $output = array();
        $parent_id = $file['parent_virtual_file_id'];
        $output[$parent_id] = true;
        while($parent_id !== '-1'){
            $q = $this->db->get_where('virtual_file', array('virtual_file_id'=>$parent_id));
            $r = $q->result_array();
            if(sizeof($r)===0){
                break;
            }
            $parent = $r[0];
            $parent_id = $parent['parent_virtual_file_id'];
            $output[$parent_id] = true;
        }
        return $output;
    }
    /*
    this method returns a link to the shared/not shared file on the cloud storage provider it resides on, this only works for files that are
    not allowed to be chunked(configured files).
    we will look at the user's permission to the file on our storage and decide what kind of link(edit or preview) the user will get
    
    output: array(
        status: 'success' or 'error' or 'need_account'
        errorMessage: a message describing why the user can't get a link to the file, such as "You don't have the permissions to access the file"
        type: 'edit' or 'preview'
        link: the url we return to the user agent
    )
    */
    public function getEditViewLink($user, $virtual_file_id){
        //check what kind of permission does the user have to the file
        $role = $this->getUserRoleForVirtualFile($virtual_file_id, $user);
        if($role == false){
            return array('status'=>'error', 'errorMessage'=>'你沒有存取該檔案之權利');
        }else{
            //check if the virtual file is a file that can be previwed/edited online, for instance, folders cannot be previewed
            $vfile = $this->getVirtualFileData($virtual_file_id);
            if($vfile['file_type'] == 'folder') return array('status'=>'error', 'errorMessage'=>'此類型檔案無法線上編輯或者預覽');
            //get the owner account, and the storage_id of the file
            $storage_data = $this->getStorageDataForVirtualFile($virtual_file_id);
            //check if there is only 1 storage file, if not, reject the preview/edit
            if(sizeof($storage_data)>1) return array('status'=>'error', 'errorMessage'=>'檔案已被切割，無法在線上預覽');
            else if(sizeof($storage_data)==0) return array('status'=>'error', 'errorMessage'=>'檔案在雲端硬碟提供者上的資料消失了');
            //only 1 storage file, but we still need to check if the file is hosted on a provider that 'need_account_for_preview'/'need_account_for_edit'
            $provider_info = $this->config->item('provider_info');
            $owner_account = $storage_data[0];//here we directly use the joined storage_account and storage_file data as the storage_account
            $provider = $owner_account['token_type'];
            //get the number of user accounts of the provider
            $num_user_accounts = sizeof($this->storageAccountModel->getStorageAccountsOfProvider($user, $provider));
            //it works kinda like polymorphism in O.O., since the array has all the fields of the storage account, it will work.
            $storage_id = $storage_data[0]['storage_id'];
            $link_type = '';
            $link_url= '';
            if($this->roleCompare($role, 'writer')>=0){
                if($provider_info[$provider]['need_account_for_edit']&&$num_user_accounts==0){
                    //the provider's link needs the user to be logged in to one of their accounts
                    //but the user doesn't have an account on the provider, output error
                    return array(
                        'status'=>'need_account',
                        'errorMessage'=>'該檔案存放在'.$provider.'雲端硬碟上，需要您以該種帳號登入才能線上編輯，請連結至少一個'.$provider.'帳號'
                    );
                }
                //return edit link
                $link_type = 'edit';
                $link_url = $this->cloudStorageModel->getEditLink($owner_account, $storage_id);
            }else{
                if($provider_info[$provider]['need_account_for_edit']&&$num_user_accounts==0){
                    //the provider's link needs the user to be logged in to one of their accounts
                    //but the user doesn't have an account on the provider, output error
                    return array(
                        'status'=>'need_account',
                        'errorMessage'=>'該檔案存放在'.$provider.'雲端硬碟上，需要您以該種帳號登入才能線上預覽，請連結至少一個'.$provider.'帳號'
                    );
                }
                //return preview link
                $link_type = 'preview';
                $link_url = $this->cloudStorageModel->getPreviewLink($owner_account, $storage_id);
            }
            return array(
                'status'=>'success',
                'type'=>$link_type,
                'link'=>$link_url
            );
        }
    }
    /*
        gets the download link of a file, we will try to see if the user has access to the file first, if not we will return a error.
        if the file is chunked up already, then we will redirect the data from the server, so we will reply with a url on our system.
        output format:
        array(
            'status'=>'success' or 'error'
            'errorMessage'=>'the error'
            'link'=>link url on the storage provider, or on our system, files.downloadFromServer
        );
    */
    public function getDownloadLink($virtual_file_id, $user){
        if($this->hasAccess($user, $virtual_file_id, 'reader')){
            //determine if the file can be downloaded on the provider, or should our system download it and redirect it to the user
            //get the storage_files
            $sfiles = $this->getStorageFilesForVirtualFileId($virtual_file_id);
            if(sizeof($sfiles)>1){
                //download from server
                return array(
                    'status'=>'success',
                    'link'=>base_url().'index.php/files/downloadfromserver/'.$virtual_file_id
                );
            }else if(sizeof($sfiles)==0){
                //error, there is no storage file data, this should never happen
                return array(
                    'status'=>'error',
                    'link'=>'找不到此虛擬檔案之實體檔案資料'
                );
            }else{// == 1, can download from provider directly
                $sfile = $sfiles[0];
                $storage_id = $sfile['storage_id'];
                $storage_account = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
                $link_res = $this->cloudStorageModel->getDownloadLink($storage_id, $storage_account, $user);
                return $link_res;
            }
        }else{
            return array(
                'status'=>'error',
                'errorMessage'=>'你沒有下載該檔案的權利!'
            );
        }
    }
    /*
        download a virtual file's content from the cloud storage providers, different from cloudStorageModel->download, this function will download
        virtual files that were split up and reconstruct the file data if needed.
        in cloudStrorageModel->download, the target is a storage file, not a virtual file
        $fh is a opened file with write mode enabled, we will start writing to the file at it's current position
        Note that the $fh will be rewinded after this function
        returns: nothing
    */
    public function getVirtualFileContent($virtual_file_id, $fh){
        //download each storage file of this virtual file sequentially
        //get all the storage files in offset order
        $sfiles = $this->getStorageFilesForVirtualFileIdSorted($virtual_file_id, 'byte_offset_start', true);
        foreach($sfiles as $sfile){
            $storage_account = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
            $storage_id = $sfile['storage_id'];
            $this->cloudStorageModel->downloadFile($storage_account, $storage_id, $fh);
            //the $fh is rewinded in each call of downloadFile, so we need to seek end to append to it
            fseek($fh, 0, SEEK_END);
        }
        rewind($fh);
    }
    public function isDescendantOfFiles($virtual_file_id, $files){
        $set = array_to_map('virtual_file_id', $files);
        $r = $this->getVirtualFileData($virtual_file_id);
        if($r == false) return false;
        $target = $r;
        $ancestors = $this->getAncestorFileIds($target);
        foreach($ancestors as $id => $whatever){
            if(array_key_exists($id, $set)){
                return true;
            }
        }
        return false;
    }
    public function constructVirtualFileMap($vfiles){
        return array_to_map('virtual_file_id', $vfiles);
    }
	//new system--------------------------------------------------------------------------------------------
    public function deleteDirectory($file){
        $children = $this->getChildrenVirtualFiles($file['file_id']);
        foreach($children as $child){
            if ($child['type'] == 'dir') {
                $this->deleteDirectory($child);
            } else {
                $this->deleteFile($child);
            }
        }
        $this->db->delete('files', array('file_id'=>$file['file_id']));
    }
    public function deleteFile($file){
        $access = $file['access'];
        if($access == 'owner'){
            //delete all files that have the same storage_id and is using the same storage
            $q = $this->db->get_where('accounts', array('account_data_id'=>$file['account_data_id']));
            $r = $q->result_array();
            if(sizeof($r)>0){
                $sa = $r[0];
                $provider = $sa['token_type'];
                //actually do the delete
                $this->db->select('files.file_id');
                $this->db->from('files');
                $this->db->join('accounts', 'files.account_data_id = accounts.account_data_id', 'left');
                $this->db->where(array('files.storage_id'=>$file['storage_id'], 'accounts.token_type'=>$provider));
                $q = $this->db->get();
                $r = $q->result_array();
                foreach($r as $dup){
                    //$this->db->delete('files', array('file_id'=>$dup['file_id']));
                    $this->simpleDeleteFile($dup['file_id']);
                }
            }
        }else{
            //delete only this file
            $this->simpleDeleteFile($file['file_id']);
        }
    }
    public function simpleDeleteFile($file_id){
        return $this->db->delete('files', array('file_id'=>$file_id));
    }
    public function deleteFileGeneric($file_id){
        /*
        different for files and dirs
        dirs:
        they are constructed only for our system, it doesn't actually exist in the storage account.
        for shared folders, some writer/readers might move their files under a shared folder that they
        do not own. When the owner deletes it, we should not delete the folder of those.
        files:
        when the owner deletes, all copies of the file should be deleted
        */
        $q = $this->db->get_where('virtual_file', array('virtual_file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r)>0){
            $file = $r[0];
            $filetype = $file['file_type'];
            if($filetype == 'folder'){
                $this->deleteDirectory($file);
            }else{
                $this->deleteFile($file);
            }
        }
        /*
        $this->db->from('files');
        $this->db->join('accounts', 'files.account_data_id = accounts.account_data_id', 'left');
        $this->db->where(array('files.file_id'=>$file_id));
        $q = $this->db->get();
        //$q = $this->db->get_where('files', array('file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r)>0){
            $file = $r[0];
            //take action according to access type
            if($file['access'] ==  'owner'){
                //delete all the copies
                $storage_provider = $file[]
                $this->db->delete('files', array('storage_id'=>$file['storage_id']));
            } else {
                //delete only the copy for this user
                
            }
        }
        */
    }
    
    public function getFileTree($user, $root_dir_id, $recurse){
        $output = array();
        if($recurse){
            $nodes = $this->getChildren($user,$root_dir_id);
            foreach($nodes as $node){
                $output[] = $this->constructTree($node);
            }
        }else{
            $output = $this->getChildren($user,$root_dir_id);
        }
        return $output;
    }
    public function setParentForFileArray($files, $parent_file_id){
        if(!$this->isDirectory($parent_file_id)){
            return false;
        }
        foreach($files as $file){
            $id = $file['file_id'];
            $this->db->update('files',array('parent_file_id'=>$parent_file_id), array('file_id'=>$id));
        }
    }
    public function isDirectory($file_id){
        if($file_id==-1) return true;
        $q = $this->db->get_where('files',array('virtual_file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r)>0){
            if($r[0]['file_type']=='folder') return true;
        }
        return false;
    }
    private function constructTree($node){
        $children = $this->getChildren($node['account'], $node['virtual_file_id']);
        $node['children'] = array();
        if(sizeof($children)>0){
            foreach($children as $child){
                $node['children'][]=$this->constructTree($child);
            }
        }
        return $node;
    }
    private function getChildren($user, $rootdir_file_id){//no recurse
        $output = array('file_id'=>0,'file_content'=>array());
        //get the files under a folder of a user
        $this->db->from('files');
        $this->db->join('accounts', 'files.account_data_id = accounts.account_data_id', 'left');
        $this->db->where(array('accounts.account' => $user, 'files.parent_file_id' => $rootdir_file_id));
        $q = $this->db->get();
        $files = $q->result_array();
        //---
        return $files;
        
    }
    public function hasAccessToDir($user, $dir_id, $role){
        if($dir_id == -1) return true;
        $q = $this->db->get_where('files', array('file_id'=>$dir_id));
        $r = $q->result_array();
        if(sizeof($r)==0) return false;
        $file = $r[0];
        $account = $file['account_data_id'];
        $q = $this->db->get_where('accounts', array('account_data_id'=>$account));
        $r = $q->result_array();
        if(sizeof($r)==0) return false;
        $acc = $r[0];
        return (($file['type'] === 'dir')&&($acc['account'] === $user));
    }
    
}