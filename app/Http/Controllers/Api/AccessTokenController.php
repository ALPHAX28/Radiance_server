<?php

namespace App\Http\Controllers\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use \Kreait\Firebase\Contract\Messaging;
use Illuminate\Support\Facades\Validator;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\ApnsConfig;
use Error;

class AccessTokenController extends Controller
{
    public function get_rtc_token(Request $request){
        $channelName = $request->input('channel_name');
        $appID = "f686b6c1cd1c4f4e91a99b1cc9fc2e31";
        $appCertificate = "fec3b171cbf74c57a8687e6cb25bc74a";
        $uid = 0;
        $expireTimeInSeconds = 7200;
        $accessToken = new AccessToken2($appID, $appCertificate,$expireTimeInSeconds);
        $serviceRtc = new ServiceRtc($channelName, $uid);
        $serviceRtc->addPrivilege($serviceRtc::PRIVILEGE_JOIN_CHANNEL, $expireTimeInSeconds);
        $accessToken->addService($serviceRtc);
        $token = $accessToken->build();
        if(!empty($token)){
            return['code'=>0,"data"=>$token, "msg"=>"success"];
        }
        return['code'=>-1,"data"=>"", "msg"=>"token error"];

        
    }

    public function send_notice(Request $request){
        $user_token = $request->user_token;
        $user_avatar = $request->user_avatar;
        $user_name = $request->user_name;
        $to_token = $request->input('to_token');
        $call_type = $request->input('call_type');
        $doc_id = $request->input('doc_id');
        $to_avatar = $request->input('to_avatar');
        $to_name = $request->input('to_name');
        if(empty($doc_id)){
            $doc_id="";
        }
        $res = DB::table("users")->select("avatar","name","token","fcmtoken")->where("token","=",$to_token)->first();

        if(empty($res)){
        return ['code'=>-1,"data"=>"", "msg"=>"user does not exist"];

        }
        $device_token = $res->fcmtoken;
        try{
            
            if(!empty($device_token)){
                $messaging = app("firebase.messaging");
                if($call_type=="cancel"){
                    $message = CloudMessage::fromArray([
                        'token'=>$device_token,
                        'data'=>[
                            'token'=>$user_token,
                            'avatar'=>$user_avatar,
                            'name'=>$user_name,
                            'doc_id'=>$doc_id,
                            'call_type'=>$call_type,
                        ]
                    ]);
                    $messaging->send($message);
                    
                }elseif($call_type=="voice"){
                    $message = CloudMessage::fromArray([
                        'token'=>$device_token,
                        'data'=>[
                            'token'=>$user_token,
                            'avatar'=>$user_avatar,
                            'name'=>$user_name,
                            'doc_id'=>$doc_id,
                            'call_type'=>$call_type,
                        ],
                        'android'=>[
                            'priority'=>'high',
                            'notification'=>[
                                'channel_id'=>'xxx',
                                'title'=>'Voice call made by '.$user_name,
                                'body'=>'Please click to answer the voice call'
                            ],
                        ]
                    ]);
                    
                }elseif($call_type=="video"){
                    $message = CloudMessage::fromArray([
                        'token'=>$device_token,
                        'data'=>[
                            'token'=>$user_token,
                            'avatar'=>$user_avatar,
                            'name'=>$user_name,
                            'doc_id'=>$doc_id,
                            'call_type'=>$call_type,
                        ],
                        'android'=>[
                            'priority'=>'high',
                            'notification'=>[
                                'channel_id'=>'xxx',
                                'title'=>'Video call made by '.$user_name,
                                'body'=>'Please click to answer the video call'
                            ],
                        ]
                    ]);
                }elseif($call_type=="text"){
                    $message = CloudMessage::fromArray([
                        'token' => $deviceToken, // optional
                       'data' => [
                           'token' => $user_token,
                           'avatar' => $user_avatar,
                           'name' => $user_name,
                           'call_type' => $call_type,
                       ],
                       'android' => [
                           "priority" => "high",
                           "notification" => [
                               "channel_id"=> "com.dbestech.chatty.message",
                               'title' => "Message made by ".$user_name,
                               'body' => "Please click to answer the Message",
                               ]
                           ],
                           'apns' => [
                           // https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#apnsconfig
                           'headers' => [
                               'apns-priority' => '10',
                           ],
                           'payload' => [
                               'aps' => [
                                   'alert' => [
                                       'title' => "Message made by ".$user_name,
                                       'body' => "Please click to answer the Message",
                                   ],
                                   'badge' => 1,
                                   'sound' =>'ding.caf'
                               ],
                           ],
                       ],
                       ]);
                       
                      $messaging->send($message);
                }
                
                $messaging->send($message);
                return ['code'=>0,"data"=>$to_token, "msg"=>"success"];
                

            }

            else{
            return ['code'=>-1,"data"=>"", "msg"=>"device token does not exist"];

            }


        }catch(\Exception $e){
            return ['code'=>-1,"data"=>"", "msg"=>(string)$e];

        }

    
    }

    public function bind_fcmtoken(Request $request){
        $token = $request->user_token;
        $fcmtoken = $request->input('fcmtoken');
        if(empty($fcmtoken)){
            return ['code'=>-1,"data"=>"", "msg"=>"fcmtoken is empty"];
        }
        DB::table("users")->where("token","=",$token)->update(["fcmtoken"=>$fcmtoken]);
        return ['code'=>0,"data"=>$token, "msg"=>"success"];
    }

    public function upload_photo(Request $request){
        $file = $request->file('file');
        try{
            $extension = $file->getClientOriginalExtension();
            $fullFileName = uniqid().'.'.$extension;
            $timeDir = date('Ymd');
            $file->storeAs($timeDir,$fullFileName,["disk"=>"public"]);
            $url = env("APP_URL")."/uploads/".$timeDir."/".$fullFileName;
            return ['code'=>0,"data"=>$url, "msg"=>"success image upload"];
        }catch(\Exception $e){
            return ['code'=>-1,"data"=>"", "msg"=>(string)$e];
        }
    }

    public function get_profile(Request $request){
        $token = $request->user_token;
        $res = DB::table("users")->select("avatar","name","description","online")->where("token","=",$token)->first();
       
        return ["code" => 0, "data" => $res, "msg" => "success"];
   }
   
   public function update_profile(Request $request){
     $token = $request->user_token;
 
     $validator = Validator::make($request->all(), [
       'online' => 'required',
       'description' => 'required',
       'name' => 'required',
       'avatar' => 'required',
     ]);
     if ($validator->fails()) {
       return ["code" => -1, "data" => "", "msg" => $validator->errors()->first()];
       print_r($validator->errors());
     }
     try {
       
       $validated = $validator->validated();
       
       $map=[];
       $map["token"] = $token;
 
       $res = DB::table("users")->where($map)->first();
       if(!empty($res)){
   
         $validated["updated_at"] = Carbon::now();
         DB::table("users")->where($map)->update($validated);
       
         return ["code" => 0, "data" => "", "msg" => "success"];
         print("success");
       }
 
       return ["code" => -1, "data" => "", "msg" => "error"];
 
     } catch (Exception $e) {
       return ["code" => -1, "data" => "", "msg" => "error"];
     }
   }

    public function contact(Request $request){
        $token = $request->user_token;
        $res = DB::table("users")->select(
            "avatar",
            "description",
            "online",
            "token",
            "name",
        )->where("token","!=",$token)->get();

        return ["code"=>0,'data'=>$res,"msg"=>"got all users info"];
  }

}

class Service
{
    public $type;
    public $privileges;

    public function __construct($serviceType)
    {
        $this->type = $serviceType;
    }

    public function addPrivilege($privilege, $expire)
    {
        $this->privileges[$privilege] = $expire;
    }

    public function getServiceType()
    {
        return $this->type;
    }

    public function pack()
    {
        return Util::packUint16($this->type) . Util::packMapUint32($this->privileges);
    }

    public function unpack(&$data)
    {
        $this->privileges = Util::unpackMapUint32($data);
    }
}

class ServiceRtc extends Service
{
    const SERVICE_TYPE = 1;
    const PRIVILEGE_JOIN_CHANNEL = 1;
    const PRIVILEGE_PUBLISH_AUDIO_STREAM = 2;
    const PRIVILEGE_PUBLISH_VIDEO_STREAM = 3;
    const PRIVILEGE_PUBLISH_DATA_STREAM = 4;
    public $channelName;
    public $uid;

    public function __construct($channelName = "", $uid = "")
    {
        parent::__construct(self::SERVICE_TYPE);
        $this->channelName = $channelName;
        $this->uid = $uid;
    }

    public function pack()
    {
        return parent::pack() . Util::packString($this->channelName) . Util::packString($this->uid);
    }

    public function unpack(&$data)
    {
        parent::unpack($data);
        $this->channelName = Util::unpackString($data);
        $this->uid = Util::unpackString($data);
    }
}

class ServiceRtm extends Service
{
    const SERVICE_TYPE = 2;
    const PRIVILEGE_LOGIN = 1;
    public $userId;

    public function __construct($userId = "")
    {
        parent::__construct(self::SERVICE_TYPE);
        $this->userId = $userId;
    }

    public function pack()
    {
        return parent::pack() . Util::packString($this->userId);
    }

    public function unpack(&$data)
    {
        parent::unpack($data);
        $this->userId = Util::unpackString($data);
    }
}

class ServiceFpa extends Service
{
    const SERVICE_TYPE = 4;
    const PRIVILEGE_LOGIN = 1;

    public function __construct()
    {
        parent::__construct(self::SERVICE_TYPE);
    }

    public function pack()
    {
        return parent::pack();
    }

    public function unpack(&$data)
    {
        parent::unpack($data);
    }
}

class ServiceChat extends Service
{
    const SERVICE_TYPE = 5;
    const PRIVILEGE_USER = 1;
    const PRIVILEGE_APP = 2;
    public $userId;

    public function __construct($userId = "")
    {
        parent::__construct(self::SERVICE_TYPE);
        $this->userId = $userId;
    }

    public function pack()
    {
        return parent::pack() . Util::packString($this->userId);
    }

    public function unpack(&$data)
    {
        parent::unpack($data);
        $this->userId = Util::unpackString($data);
    }
}

class ServiceEducation extends Service
{
    const SERVICE_TYPE = 7;
    const PRIVILEGE_ROOM_USER = 1;
    const PRIVILEGE_USER = 2;
    const PRIVILEGE_APP = 3;

    public $roomUuid;
    public $userUuid;
    public $role;


    public function __construct($roomUuid = "", $userUuid = "", $role = -1)
    {
        parent::__construct(self::SERVICE_TYPE);
        $this->roomUuid = $roomUuid;
        $this->userUuid = $userUuid;
        $this->role = $role;
    }

    public function pack()
    {
        return parent::pack() . Util::packString($this->roomUuid) . Util::packString($this->userUuid) . Util::packInt16($this->role);
    }

    public function unpack(&$data)
    {
        parent::unpack($data);
        $this->roomUuid = Util::unpackString($data);
        $this->userUuid = Util::unpackString($data);
        $this->role = Util::unpackInt16($data);
    }
}

class AccessToken2
{
    const VERSION = "007";
    const VERSION_LENGTH = 3;
    public $appCert;
    public $appId;
    public $expire;
    public $issueTs;
    public $salt;
    public $services = [];

    public function __construct($appId = "", $appCert = "", $expire = 900)
    {
        $this->appId = $appId;
        $this->appCert = $appCert;
        $this->expire = $expire;
        $this->issueTs = time();
        $this->salt = rand(1, 99999999);
    }

    public function addService($service)
    {
        $this->services[$service->getServiceType()] = $service;
    }

    public function build()
    {
        if (!self::isUUid($this->appId) || !self::isUUid($this->appCert)) {
            return "";
        }

        $signing = $this->getSign();
        $data = Util::packString($this->appId) . Util::packUint32($this->issueTs) . Util::packUint32($this->expire)
            . Util::packUint32($this->salt) . Util::packUint16(count($this->services));

        ksort($this->services);
        foreach ($this->services as $key => $service) {
            $data .= $service->pack();
        }

        $signature = hash_hmac("sha256", $data, $signing, true);

        return self::getVersion() . base64_encode(zlib_encode(Util::packString($signature) . $data, ZLIB_ENCODING_DEFLATE));
    }

    public function getSign()
    {
        $hh = hash_hmac("sha256", $this->appCert, Util::packUint32($this->issueTs), true);
        return hash_hmac("sha256", $hh, Util::packUint32($this->salt), true);
    }

    public static function getVersion()
    {
        return self::VERSION;
    }

    public static function isUUid($str)
    {
        if (strlen($str) != 32) {
            return false;
        }
        return ctype_xdigit($str);
    }

    public function parse($token)
    {
        if (substr($token, 0, self::VERSION_LENGTH) != self::getVersion()) {
            return false;
        }

        $data = zlib_decode(base64_decode(substr($token, self::VERSION_LENGTH)));
        $signature = Util::unpackString($data);
        $this->appId = Util::unpackString($data);
        $this->issueTs = Util::unpackUint32($data);
        $this->expire = Util::unpackUint32($data);
        $this->salt = Util::unpackUint32($data);
        $serviceNum = Util::unpackUint16($data);

        $servicesObj = [
            ServiceRtc::SERVICE_TYPE => new ServiceRtc(),
            ServiceRtm::SERVICE_TYPE => new ServiceRtm(),
            ServiceFpa::SERVICE_TYPE => new ServiceFpa(),
            ServiceChat::SERVICE_TYPE => new ServiceChat(),
            ServiceEducation::SERVICE_TYPE => new ServiceEducation(),
        ];
        for ($i = 0; $i < $serviceNum; $i++) {
            $serviceTye = Util::unpackUint16($data);
            $service = $servicesObj[$serviceTye];
            if ($service == null) {
                return false;
            }
            $service->unpack($data);
            $this->services[$serviceTye] = $service;
        }
        return true;
    }
}

class Util
{
    public static function assertEqual($expected, $actual)
    {
        $debug = debug_backtrace();
        $info = "\n- File:" . basename($debug[1]["file"]) . ", Func:" . $debug[1]["function"] . ", Line:" . $debug[1]["line"];
        if ($expected != $actual) {
            echo $info . "\n  Assert failed" . "\n    Expected :" . $expected . "\n    Actual   :" . $actual;
        } else {
            echo $info . "\n  Assert ok";
        }
    }

    public static function packUint16($x)
    {
        return pack("v", $x);
    }

    public static function unpackUint16(&$data)
    {
        $up = unpack("v", substr($data, 0, 2));
        $data = substr($data, 2);
        return $up[1];
    }

    public static function packUint32($x)
    {
        return pack("V", $x);
    }

    public static function unpackUint32(&$data)
    {
        $up = unpack("V", substr($data, 0, 4));
        $data = substr($data, 4);
        return $up[1];
    }

    public static function packInt16($x)
    {
        return pack("s", $x);
    }

    public static function unpackInt16(&$data)
    {
        $up = unpack("s", substr($data, 0, 2));
        $data = substr($data, 2);
        return $up[1];
    }

    public static function packString($str)
    {
        return self::packUint16(strlen($str)) . $str;
    }

    public static function unpackString(&$data)
    {
        $len = self::unpackUint16($data);
        $up = unpack("C*", substr($data, 0, $len));
        $data = substr($data, $len);
        return implode(array_map("chr", $up));
    }

    public static function packMapUint32($arr)
    {
        ksort($arr);
        $kv = "";
        foreach ($arr as $key => $val) {
            $kv .= self::packUint16($key) . self::packUint32($val);
        }
        return self::packUint16(count($arr)) . $kv;
    }

    public static function unpackMapUint32(&$data)
    {
        $len = self::unpackUint16($data);
        $arr = [];
        for ($i = 0; $i < $len; $i++) {
            $arr[self::unpackUint16($data)] = self::unpackUint32($data);
        }
        return $arr;
    }
}
