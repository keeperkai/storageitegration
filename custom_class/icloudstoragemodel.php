<?php
interface ICloudStorageModel
{
    public function __construct()
    {
        parent::__construct();
    }
	public function getAccountQuotaInfo($access_token);
	//gets the quota info of an account
	//output: array(
	//'free': the free quota left in bytes
	//'used': the used quota in bytes
	//'total': total quota for this account
}