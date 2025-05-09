<?php 

/**
 * Member Class 
 * 
 * @package 	Controller
 * @subpackage	Workspaces
 * @author 		Irvan Av
 * @copyright   Botika
 * @link 		https://botika.online/
 * 
 **/

namespace App\Controllers\Workspaces;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Framework\Exception\HttpException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Member extends \Framework\Controller
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var array
     */
    protected $arguments;

    /**
     * @var Variable for Models
     */
    protected $modelAuth;
    protected $modelWorkspaces;
    protected $modelMembers;
    protected $modelBots;

    public function __construct(\App\Models\Users\Auths $modelAuth, \App\Models\Workspaces\Workspaces $modelWorkspaces, \App\Models\Workspaces\Members $modelMembers, \App\Models\Bots\Bots $modelBots) {
         $this->modelAuth = $modelAuth;
         $this->modelWorkspaces = $modelWorkspaces;
         $this->modelMembers = $modelMembers;
         $this->modelBots = $modelBots;
     }


    /**
     * Invite member to workspace
     * 
     * @param string $email, string $role (administrator,supervisor,agent), string $workspace (workspace id)  
     * @return json
     */
    public function inviteMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request = $request;
        $this->response = $response;
        $this->arguments = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = [
            'email' => 'email|required',
            'workspace' => 'alpha_num|required',
            'role' => 'in:administrator,supervisor,agent|required',
            'force' => 'boolean'
        ];

        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $body['email']  = \App\Helpers\xssClean($body['email']);
        $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(400, json_encode(['result' => 'error', 'message' => 'Invalid email format']));
        }
     
        $role   = \App\Helpers\xssClean($body['role']);
        $workspace = \App\Helpers\xssClean($body['workspace']);
        $force  = isset($body['force']) ? \App\Helpers\xssClean($body['force']) : false;

        # cek account
        $checkAccount = $this->modelAuth->get(['email' => $email]);
        # jika sudah ada account, cek apakah member di workspace lain atau punya role lain
        if($checkAccount && !$force){
            $checkMember = $this->modelWorkspaces->checkWorkspace($checkAccount[0]->account_id);
            if($checkMember) return $this->response(200, json_encode(['result' => 'error', 'message' => 'This email already registered in another workspace']));
        }

        # cek workspace
        $checkWorkspace = (array) $this->modelWorkspaces->getWorkspaceDetail($workspace);
        if(!$checkWorkspace) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Invalid workspace']));
        
        # cek invitation sebelumnya, jika ada set expired
        $checkInvitation = $this->modelMembers->get(['workspace_id' => $workspace, 'address' => $email, 'status' => 'unsolved'], 'botika_omny_account_info_confirmation');
        if($checkInvitation) $this->modelMembers->update(['workspace_id' => $workspace, 'address' => $email, 'status' => 'unsolved'], ['status' => 'expired'], 'botika_omny_account_info_confirmation');

        /**
         * Check slot agent
         */
        $config = new \App\Config\Config();
        $logger = \Framework\Libraries::logger('Member - check account package', 'billing');
        # hit callback payment
        $url = $config->defaultServer('FEATURES').'/account/package';
        $header = ['Authorization: Bearer '.$checkWorkspace['workspace_token']];
        $getpackage = json_decode(\App\Helpers\getContent($url, '', 'GET', $header), true);
        $logger->info('Get workspace package', ['url' => $url, 'param' => $header, 'result' => $getpackage]);
        
        if(!$getpackage || ($getpackage && isset($getpackage['error']))) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Failed get workspace package']));
        $total = 0;
        if(!empty($getpackage['package'])){
            $index = array_search('AGENT', array_column($getpackage['features'], 'content'));
            $total = (int) $getpackage['features'][$index]['total'] ?? 0;
        }
        $used = 0; $pending = 0;
        $available = 0;
        
        # hitung total member
        $member = $this->modelMembers->getMemberWorkspace($workspace);
        if($member) $used = (int) count($member);
        # hitung pending invitation
        $invitation = $this->modelMembers->getPendingInvitation($workspace);
        if($invitation) $pending = (int) count(array_filter($invitation, function ($var) {
            return ($var['status'] == 'pending') ?? 0;
        }));
        
        $available = (int) $total - ($used + $pending);
        
        # jika tidak ada slot
        if($available <= 0) return $this->response(200, json_encode(['result' => 'error', 'message' => "You no longer have the remaining agent slots, please buy additional packages before continuing the member invite process"]));
        
        # ambil data account
        $account = (array) $request->getAttribute('botikaAccount');
        
        $code = $config->configMember()['email']['invitation'][$role];
        if(!$code) return $this->response(200, json_encode(['result' => 'error', 'message' => "Failed to invite $email, please try again latter"]));
        $config = new \App\Config\Config();
        $data = [
            'address' => $email,
            'category' => $code,
            'expired' => date('Y-m-d H:i:s', strtotime('+1 days')),
            'status' => 'unsolved',
            'workspace_id' => $workspace
        ];
        
        //kalau file kurang dari 30 menit
        $ip_split = explode('.', \App\Helpers\getVisitorIp() !== '::1' ? \App\Helpers\getVisitorIp() : '127.0.0.1');
		$uuid = md5($email).sprintf('%02x%02x%02x%02x', $ip_split[0], $ip_split[1], $ip_split[2], $ip_split[3]);        
        
		$directory = getcwd().'/var/cache/invite-member';
		if(!is_dir($directory)) mkdir($directory, 0777, true);

		# create cache
		$file = $directory.'/'. $uuid;

		if(file_exists($file)) {
			$diff = (strtotime('now') - filemtime($file));
			$recurring = intval(file_get_contents($file));
			if ($recurring >= 3 && $diff < 1800) {
				return $this->response(200, json_encode(['result' => 'error', 'message' => "Maximum 3x attempt exceeded, Please wait for 30 minutes"]));
			} else {
				$fh = fopen($file, 'w');
				fwrite($fh, (intval($recurring) + 1));
				fclose($fh);
			}
		}else{
			$fh = fopen($file, 'w');
			fwrite($fh, 1);
			fclose($fh);
		}
        
        $content = \App\Helpers\loadView(BASEPATH . '/public/assets/email_invitation.php', ['inviter' => $account['email'], 'website' => $config->defaultServer('WEBSITE')]);
        \App\Helpers\sendEmail(
            ['email' => 'no-reply@botika.online'], 
            [['email' => $email]], 
            [], 
            [], 
            'You have been invited to join Omnibotika', 
            $content
        );

        # set invitation
        $setInvitation = $this->modelMembers->set($data, 'botika_omny_account_info_confirmation');
        if(!$setInvitation) return $this->response(200, json_encode(['result' => 'error', 'message' => "Failed to invite $email, please try again latter"]));

        return $this->response(200, json_encode(['result' => 'success', 'message' => htmlspecialchars("Successfully sending invitation to $email", ENT_QUOTES, 'UTF-8')]));
    }
    
    /**
     * Invite member to workspace
     * 
     * @param string $email, string $role (administrator,supervisor,agent), string $workspace (workspace id)  
     * @return json
     */
    public function updateMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request = $request;
        $this->response = $response;
        $this->arguments = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = [
            'id' => 'alpha_num|required',
            'workspace' => 'alpha_num|required',
            'role' => 'in:administrator,supervisor,agent|required'
        ];

        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $id = \App\Helpers\xssClean($body['id']);
        $role = \App\Helpers\xssClean($body['role']);
        $wid = \App\Helpers\xssClean($body['workspace']);

        # check available role
        $checkRole  = $this->modelMembers->isWorkspaceOwner($id, $wid);
        if(!$checkRole) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Cannot found account']));
        
        $getRole = $this->modelMembers->get(['workspace_id' => $wid, 'workspace_role_name' => $role], 'botika_omny_workspace_role');
        if(!$getRole) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Invalid role']));
        
        $update = $this->modelMembers->update(['member_role_id' => $checkRole->member_role_id], ['workspace_role_id' => $getRole[0]->workspace_role_id, 'workspace_role_idx' => $getRole[0]->workspace_role_idx], 'botika_omny_workspace_member_role');
        if(!$update) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Failed to update role']));
        $botWorkspace = $this->modelBots->getWorkspaceBots($wid);
        if($botWorkspace) $updateBotAccess = $this->modelMembers->update(['account_id' => $id, 'bot_id' => $botWorkspace[0]->bot_id], ['level' => $role], 'botika_bot_access');
        return $this->response(200, json_encode(['result' => 'success', 'message' => htmlspecialchars("Successfully update role", ENT_QUOTES, 'UTF-8')]));
    }

    /**
     * Get List Member
     * @param required string $workspace (workspaceid)
     * @param optional array $role (administrator, supervisor, agent) 
     * @return json
     */
    public function getMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request      = $request;
        $this->response     = $response;
        $this->arguments    = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = [
            'workspace' => 'alpha_num|required',
            'role'      => 'array|in:administrator,supervisor,agent'
        ];

        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $workspace  = \App\Helpers\xssClean($body['workspace']);
        $role       = isset($body['role']) ? (array) $body['role'] : [];
        
        # ambil data member
        $member = $this->modelMembers->getMemberWorkspace($workspace, ['role' => $role]);
        foreach($member as $key => $val){ 
            # update last 30 menit            
            if((strtotime($val->last_active_date) < strtotime('-30 min')) && ($val->login == '1' || $val->login_status == 'Available')){
                $member[$key]->login = '0';
                $member[$key]->login_status = 'Offline';
                $update = $this->modelAuth->update(['account_id' => $val->account_id], ['login' => '0', 'login_status' => 'Offline']);
            } 
        }
        
        return $this->response(200, json_encode(['result' => 'success', 'data' => $member]));
    }
    
    /**
     * Get List Member
     * @param required string $workspace (workspaceId), string $id (accountId)
     * @return json
     */
    public function removeMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request      = $request;
        $this->response     = $response;
        $this->arguments    = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = [
            'workspace' => 'alpha_num|required',
            'id'        => 'alpha_num|required'
        ];

        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $workspace  = \App\Helpers\xssClean($body['workspace']);
        $id         = \App\Helpers\xssClean($body['id']);
        
        # check available role
        $checkRole  = $this->modelMembers->isWorkspaceOwner($id, $workspace);
        if(!$checkRole) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Cannot found account']));
        if($id == $checkRole->workspace_owner) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Cannot remove owner workspace']));
        
        # delete
        $delete = $this->modelMembers->delete(['workspace_id' => $workspace, 'account_id' => $id], 'botika_omny_workspace_member_role');
        if(!$delete) return $this->response(200, json_encode(['result' => 'error', 'message' => "Failed to remove member"]));
        # delete bot access
        $this->modelMembers->removeBotAccess($id, $checkRole->bot_id);
        return $this->response(200, json_encode(['result' => 'success', 'message' => 'successfully remove member']));
    }
        
    /**
     * Get List Pending Invitation Member
     * @param required string $workspace (workspaceid)
     * @param optional array $role (administrator, supervisor, agent) 
     * @return json
     */
    public function getPendingMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request      = $request;
        $this->response     = $response;
        $this->arguments    = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = ['workspace' => 'alpha_num|required'];
        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $wid  = \App\Helpers\xssClean($body['workspace']);
        
        # ambil data member
        $member = $this->modelMembers->getPendingInvitation($wid);
        return $this->response(200, json_encode(['result' => 'success', 'data' => $member]));
    }
    
    /**
     * Get List Pending Invitation Member
     * @param required string $workspace (workspaceid)
     * @param optional array $role (administrator, supervisor, agent) 
     * @return json
     */
    public function removePendingInvitation(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request      = $request;
        $this->response     = $response;
        $this->arguments    = $arguments;

        $body = $request->getParsedBody(); // get body request, return array

        $rules = [
            'workspace' => 'alpha_num|required',
            'email' => 'email|required'
        ];
        $validation = \Framework\Libraries::validation((array) $body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }

        $workspace  = \App\Helpers\xssClean($body['workspace']);
        $body['email']  = \App\Helpers\xssClean($body['email']);
        $email = filter_var($body['email'], FILTER_SANITIZE_EMAIL);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->response(400, json_encode(['result' => 'error', 'message' => 'Invalid email format']));
        }
        
        # cek invitation sebelumnya, jika ada set expired
        $checkInvitation = $this->modelMembers->get(['workspace_id' => $workspace, 'address' => $email, 'status' => 'unsolved'], 'botika_omny_account_info_confirmation');
        if($checkInvitation) $this->modelMembers->update(['workspace_id' => $workspace, 'address' => $email, 'status' => 'unsolved'], ['status' => 'expired'], 'botika_omny_account_info_confirmation');
        
        return $this->response(200, json_encode(['result' => 'success', 'message' => 'Successfully remove pending invitation']));
    }
    
    public function getProfile(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request = $request;
        $this->response = $response;
        $this->arguments = $arguments;

        $body = (array) $request->getParsedBody(); // get body request, return array

        $rules = [ 'id' => 'alpha_num|required'];
        $validation = \Framework\Libraries::validation($body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }
        $data = $this->modelAuth->get(['account_id' => \App\Helpers\xssClean($body['id'])]);
        if(!$data) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Account not found']));
        $output = [
            'account_id' => $data[0]->account_id,
            'username' => $data[0]->username,
            'name' => $data[0]->name,
            'email' => $data[0]->email,
            'phone' => $data[0]->phone,
            'picture_link' => $data[0]->picture_link
        ];
		return $this->response(200, json_encode(['result' => 'success', 'data' => $output]));
	}
    
	public function updateProfile(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request = $request;
        $this->response = $response;
        $this->arguments = $arguments;

        $body = (array) $request->getParsedBody(); // get body request, return array

        $rules = [
            'id' => 'alpha_num|required',
            'name' => 'regex:/^[a-zA-Z0-9\s]+$/', // aplha num space
            'email'  => 'email',
            'profile' => 'url'
        ];

        $validation = \Framework\Libraries::validation($body, $rules);
        if ( ! $validation->run()) {
            foreach($validation->errors() as $key => $message) return $this->response(200, json_encode(['result' => 'error', 'message' => $message[0]]));
        }
		
		$id = \App\Helpers\xssClean($body['id']);
		$name = isset($body['name']) ? \App\Helpers\xssClean($body['name']) : null;
		$email = isset($body['email']) ? \App\Helpers\xssClean($body['email']) : null;
		$profile = isset($body['profile']) ? \App\Helpers\xssClean($body['profile']) : null;
        $login = isset($body['login']) ? '0' : null;
        
		$param = [];
		if($name) $param['name'] = $name;
		if($email) $param['email'] = $email;
        if($profile) $param['picture_link'] = $profile;        
        if($login === '0') $param['login'] = $login;
		
		if(!$param) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Invalid parameter']));
		$update = $this->modelAuth->update(['account_id' => $id], $param);
		if(!$update) return $this->response(200, json_encode(['result' => 'error', 'message' => 'Failed to update account']));
		return $this->response(200, json_encode(['result' => 'success', 'message' => 'Successfully update account']));
	}
    
    /**
     * Api Download List Member as excel
     */
    public function downloadMember(Request $request, Response $response, array $arguments): Response
    {
        # set global variable setiap start fungsi
        $this->request = $request;
        $this->response = $response;
        $this->arguments = $arguments;
		
        $account = (array) $request->getAttribute('botikaAccount');
		$member = $this->modelMembers->getMemberWorkspace($account['workspace_id']);
        
        $directory = getcwd().'/var/upload/report';
        if(!is_dir($directory)) mkdir($directory, 0777, true);
        $filename = 'Omni_Members_'.$account['workspace_name']. '_' . \App\Helpers\randomString(10) . '.xlsx';
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Name');
        $sheet->setCellValue('B1', 'Username');
        $sheet->setCellValue('C1', 'Email');
        $sheet->setCellValue('D1', 'Role');
        $sheet->setCellValue('E1', 'Department');

        $rowCount = 2;
        foreach ($member as $key => $row) {
            $sheet->setCellValue('A' . $rowCount, $row->name ?? '-');
            $sheet->setCellValue('B' . $rowCount, $row->username ?? '-');
            $sheet->setCellValue('C' . $rowCount, $row->email ?? '-');
            $sheet->setCellValue('D' . $rowCount, $row->role ?? '-');
            $sheet->setCellValue('E' . $rowCount, $row->departement_name ?? '-');
            $rowCount++;
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($directory.'/'.$filename);
        
        $bucket = 'botmaster-files';
        $key    = 'Omnichannel/member/'.date('Y').'/'.date('m').'/'.$filename;
        $mime   = mime_content_type($directory.'/'.$filename);
        $handle = $directory.'/'.$filename; 
        $upload = \App\Helpers\uploadAws($bucket, $key, $handle, $mime);
        
        if($upload){
            unlink($handle);
            return $this->response(200, json_encode(['result' => 'success', 'url' => $upload]));
        }else{
            return $this->response(200, json_encode(['result' => 'error', 'message' => 'Failed to generate file!']));
        }        
    }
}