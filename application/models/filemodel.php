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
            $this->db->update('virtual_file', array('parent_virtual_file_id'=>$loose_file_folder['virtual_file_id']),array('virtual_file_id'=>$loosefile['virtual_file_id']));
        }
    }
    public function createLooseFilesFolderForUser($user){
        $this->db->insert('virtual_file', array('file_type'=>'folder', 'name'=>'Loose Files', 'account'=>$user));
        $q = $this->db->get_where('virtual_file', array('file_type'=>'folder', 'name'=>'Loose Files', 'account'=>$user));
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
	private function getPermissionsForVirtualFile($virtual_file_id){
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
	private function getVirtualFileData($virtual_file_id){
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
	*/
	public function propagateStorageFilePermissionToCloudStorage($sfile){
		//get the storage account
		$acc = $this->storageAccountModel->getStorageAccountWithId($sfile['storage_account_id']);
		//for all types of accounts that need to propagate permissions beforehand, do so here
		
		//get all the users that have any form of permission to the file
		$permits = $this->getPermissionsForVirtualFile($sfile['virtual_file_id']);
		foreach($permits as $perm){
			$this->cloudStorageModel->addPermissionForUser($sfile['storage_id'], $acc, $perm['account'], $perm['role']);
		}
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
	public function addPermission($virtual_file_id, $user, $role){
		$vfile = $this->getVirtualFileData($virtual_file_id);
		if($vfile){
			$type = $vfile['file_type'];
			//add permission for the file
			$this->addPermissionForSingleFile($virtual_file_id, $user, $role);
			if($type == 'folder'){//recurse
				$children = $this->getChildrenVirtualFiles($virtual_file_id);
				foreach($children as $child){
					$this->addPermission($child['virtual_file_id']);
				}
			}
		}
		
	}
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
		//if the file is hosted on google, actually add the file permission
		//just find all storage files linked to this virtual file and for the google ones, propagate the permission
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
	
	public function constructVirtualFileMap($virtual_files){
		$output = array();
		foreach($virtual_files as $vfile){
			$output[$vfile['virtual_file_id']] = $vfile;
		}
		return $output;
	}
    
    public function hasAccess($user, $file_id, $role){
        if($file_id === -1) return true;
        /*
        $q = $this->db->get_where('virtual_file', array('virtual_file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r)==0) return false;
        $file = $r[0];
        $account = $file['account_data_id'];
        $q = $this->db->get_where('accounts', array('account_data_id'=>$account));
        $r = $q->result_array();
        if(sizeof($r)==0) return false;
        $acc = $r[0];
        return ($acc['account'] === $user);
        */
        $q = $this->db->get_where('file_permissions', array('virtual_file_id'=>$file_id, 'account'=>$user));
        $r = $q->result_array();
        if(sizeof($r)>0){
            $permit = $r[0];
            if($this->roleCompare($role, $permit['role'])>=0) return true;
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
    public function getIndependentFiles($file_collection){//returns independent files, i.e. if there are file ancestors->descendants, return a collection with redundant files removed
        //input should be an array of array, each lower array containing a associative key 'file_id'
        $output = $file_collection;
        $output = $this->constructVirtualFileMap($output);
        $set = $this->constructVirtualFileMap($file_collection);
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
    public function getAncestorFileIds($file){// outputs a map with fileIds as key and true as value
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
    public function isDescendantOfFiles($file_id, $files){
        $set = $this->getAsFileIdKeyArray($files);
        $q = $this->db->get_where('files', array('file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r) === 0) return false;
        $target = $r[0];
        $ancestors = $this->getAncestorFileIds($target);
        foreach($ancestors as $id => $whatever){
            if(array_key_exists($id, $set)){
                return true;
            }
        }
        return false;
    }
    
    public function getAsFileIdKeyArray($file_collection){
        $output = array();
        foreach($file_collection as $file){
            $output[$file['file_id']] = $file;
        }
        return $output;
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
        $q = $this->db->get_where('files',array('file_id'=>$file_id));
        $r = $q->result_array();
        if(sizeof($r)>0){
            if($r[0]['type']=='dir') return true;
        }
        return false;
    }
    private function constructTree($node){
        $children = $this->getChildren($node['account'], $node['file_id']);
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