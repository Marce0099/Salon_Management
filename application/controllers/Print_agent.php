<?php if( !defined('BASEPATH') ){ exit(); }

/**
 * API called by the print agent program (agent/PosPrintAgent.ps1) running on a PC with a USB printer.
 * No browser session: the agent sends its token in the `X-Agent-Token` header (only the sha256 is stored in print_agents).
 *
 *   GET  /print_agent/jobs?wait=15&version=1.0   -> {"jobs":[{"id_job","printer","title","payload"(base64 ESC/POS)}]}  (long poll, also the heartbeat)
 *   POST /print_agent/result  id_job, ok(1|0), error -> {"type":"success"}
 *
 * config/autoload.php skips the session library for this URL so polling does not create a ci_sessions row per request.
 */
class Print_agent extends MX_Controller{

	private $agent;

	public function __construct(){

		parent::__construct();

		$this->load->library('printing');

		$token = trim((string) $this->input->get_request_header('X-Agent-Token', TRUE));

		if( !preg_match('/^[0-9a-f]{48}$/', $token) ){
			$this->deny();
		}

		$this->agent = $this->db->where('token_hash', hash('sha256', $token))->where('active', 1)->get('print_agents')->row();

		if( !$this->agent ){
			$this->deny();
		}

		$this->printing->agentTouch($this->agent->id_agent, (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : ""), $this->input->get('version'));
	}

	private function deny(){

		sleep(1); // makes token guessing pointless
		$this->json(array('type' => 'error', 'message' => 'Unauthorized'), 401);
	}

	private function json($data, $status = 200){

		$this->output->set_status_header($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($data);
		exit;
	}

	/** Waiting jobs of this agent. Long poll: holds the request up to `wait` seconds (max 20) when nothing is waiting. */
	public function jobs(){

		$wait = min(20, max(0, (int) $this->input->get('wait')));
		@set_time_limit($wait + 15);
		$until = time() + $wait;

		do{

			$jobs = $this->printing->agentClaim($this->agent->id_agent, 10);

			if( $jobs || time() >= $until ){ break; }

			sleep(1);
			$this->printing->agentTouch($this->agent->id_agent, (isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : ""), $this->input->get('version'));
		}
		while( TRUE );

		$this->json(array('type' => 'success', 'jobs' => $jobs));
	}

	public function result(){

		if( $this->input->method() !== 'post' ){
			$this->json(array('type' => 'error', 'message' => 'POST required'), 405);
		}

		list($ok, $msg) = $this->printing->agentResult($this->agent->id_agent, (int) $this->input->post('id_job'), (int) $this->input->post('ok') === 1, (string) $this->input->post('error'));

		$this->json($ok ? array('type' => 'success') : array('type' => 'error', 'message' => $msg), $ok ? 200 : 409);
	}
}
