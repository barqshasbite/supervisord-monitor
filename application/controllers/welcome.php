<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Welcome extends MY_Controller {
    public function Index() {

        $mute = $this->input->get('mute');
        if($this->input->get('mute') == 1){
            $mute_time = time()+600;
            setcookie('mute',$mute_time,$mute_time,'/');
            Redirect();
        }
        if($this->input->get('mute')==-1){
            setcookie('mute',0,time()-1,'/');
            Redirect();
        }

        $data['muted'] = $this->input->cookie('mute');

        $data['filter'] = '';
        if(isset($_GET['filter'])) {
            $data['filter'] = $_GET['filter'];
        }

        $this->load->helper('date');
        $servers = $this->config->item('supervisor_servers');
        if(empty($servers)) {
            $data['list'] = array();
        } else {
            foreach($servers as $name=>$config){
                $data['list'][$name] = array(
                    'server' => $config,
                    'processes' => $this->_request($name,'getAllProcessInfo'),
                );
            }
        }
        $data['cfg'] = $servers;
        $this->load->view('welcome',$data);
    }
}
