<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_Controller extends CI_Controller {

	public $data = [];

	function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->library(['session','layout']);
		$this->load->helper(['url']);
	}

	public function report2column()
	{
		
		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('report2column');

		$this->load->library('Table2Column');
		
		$table = new Table2Column;
		$table->setTitle($config['format']['title']);

		$this->load->model('BaseModel');
		
		$data = $this->BaseModel->execute($config['argument'])->row();
		$table->setData($data);
		$table->setFormat($config['format']);		
		$table->setFlow($this->config->item('flow'));
		
		$table->render();

	}

	public function table()
	{
		
		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('table');

		$this->load->library('table');
		
		$table = new Table;
		
		
		$this->load->model('BaseModel');
		
		$data = $this->BaseModel->execute($config['argument'])->result();
		
		$table->setformat($config['html']);
		$table->setContent($data);
		$table->setFlow($this->config->item('flow'));
		
		$table->render();

	}

	public function read()
	{
		if(!isset($this->uri->segments[4]) && ! isset($this->session->user_id))
		{
			echo 'Id belum ditentukan';
			return false;
		}

		if( isset($this->uri->segments[4]))
		{
			$this->data['id'] = $this->uri->segments[4];	
		}
		else
		{
			$this->data['id'] = $this->session->user_id;	
		}

		//mengubah _ menjadi /
		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('read');
		//var_dump($config);
		
		$this->load->model('BaseModel');
		$data = $this->BaseModel->execute($config['argument'])->row();
		//memanggil library table
		
		$this->load->library('blog');
		
		$blog = new Blog;
		$blog->setData($data);
		$blog->setAnchor($config['anchor']);
		
		$blog->render();		
	}

	public function form()
	{
		
		$path = str_replace('_','/',$this->uri->segments[3]);

		$this->data['id'] = isset($this->uri->segments[4]) ? $this->uri->segments[4] : '';
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('form');
		
		if( isset($this->uri->segments[4]))
		{
			$this->data['id'] = $this->uri->segments[4];	
			$this->session->set_userdata('submit_id',$this->data['id']);
		}
		else if($this->session->user_id != null)
		{
			$this->data['id'] = $this->session->user_id;	
		}
		else
		{
			$this->data['id'] = '';
		}
		$data =  new StdClass;
	
		$this->session->set_userdata($path, $this->data['id']);
		if(isset($config['argument']) && $this->data['id'] != '')
		{
			$this->load->model('BaseModel');
			$data = $this->BaseModel->execute($config['argument'])->row();
		}

		$message = null;
		if(isset($this->session->validation_errors) && $this->session->validation_errors != null)
		{
			$message = $this->session->validation_errors; 	
		}
		
		$this->load->library('form');
		
		$form = new Form;

		$form->setData($data, $message);
		$form->setFormat($config['form']);
		$form->setFlow($this->config->item('flow'));
		$form->render();
	}

	function submit()
	{

		$this->load->library('form_validation');

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('submit');

		foreach($config['validation'] as $v)
		{
			$this->form_validation->set_rules($v['field'], $v['label'], $v['rules']);
		}

		$post = [];
		foreach($config['post'] as $p)
		{
			$post[$p] = $this->security->xss_clean($this->input->post($p));
		}
		
		if($this->form_validation->run() == FALSE)
		{
			$this->session->set_flashdata('set_val', (object) $post);
			$this->session->set_flashdata('validation_errors', validation_errors());
			redirect('/' . $config['redirect']['error'] . '/' .$id);
			return false;
		}

		$this->load->model('BaseModel');

		if(!isset($config['insert']['table']))
		{
			$rows=1;
		}
		else
		{
			$rows = $this->BaseModel->execute($config['check'])->num_rows();
		}

		if($rows>0)
		{
			foreach($config['update']['field'] as $f)
			{
				$field[$f] = $post[$f];
			}
				
			foreach($config['update']['where'] as $k=>$f)
			{
				$where[$k] = $this->session->{$f};
			}
			$this->BaseModel->update($config['update']['table'], $field, $where);
			$id = $this->session->user_id; 
		}
		else
		{
			if(!isset($config['insert']['table']) && isset($config['error']))
			{
				$this->session->set_flashdata('validation_errors', $config['error']['message']);
				redirect('/' . $config['error']['back']);
				return false;		
			}

			$table = $config['insert']['table'];
			foreach($config['insert']['field'] as $f)
			{
				$field[$f] = $post[$f];
			}
			if(isset($config['insert']['session']))
			{
				foreach($config['insert']['session'] as $k=>$f)
				{
					$field[$k] = $this->session->{$f};
				}
			}
			if(isset($field['password']))
			{
				$field['password'] = password_hash( $field['password'],  PASSWORD_BCRYPT, ['cost'=>12]);	
			}
			$id = $this->BaseModel->insert($config['insert']['table'], $field);
			$this->session->set_userdata($path, $id);
		}
		redirect('/' . $config['redirect']['success']);
	}

	function auth()
	{

		$this->load->library('form_validation');

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('auth');

		$this->data['email'] = $this->input->post('email');
		
		foreach($config['validation'] as $v)
		{
			$this->form_validation->set_rules($v['field'], $v['label'], $v['rules']);
		}

		if($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('validation_errors', validation_errors());
			redirect('/' . $config['redirect']['error'] );
			return false;
		}

		$this->load->model('BaseModel');

		$auth = $this->BaseModel->execute($config['argument'])->row();
		if( $auth==null )
		{
			$this->session->set_flashdata('validation_errors', 'Email tidak terdaftar');
			redirect('/' . $config['redirect']['error'] );
			return false;
		}

		if( password_verify( $this->input->post('password'), $auth->password ))
		{
			$this->data['user_id'] = $auth->id;
			foreach($this->BaseModel->execute($config['roles'])->result() as $r)
			{
				$roles[] = $r->role_id;
			}
			$user = [
				'user_id' => $auth->id,
				'roles' => $roles,
				'identity' => $this->input->post('email'),
				'logedIn' => true,
			];
					
			$this->session->set_userdata($user);
			$this->session->set_flashdata('validation_errors', 'Login Berhasil');
			redirect('/' . $config['redirect']['success']);
			return false;
		}
		
		$this->session->set_flashdata('validation_errors', 'Password salah');
		redirect('/' . $config['redirect']['error'] );

	}

	function sendToken()
	{

		$this->load->library('form_validation');
		$this->load->library('SendMail');
		
		$mail = new SendMail;

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		$config = $this->config->item('sendToken');
		
		$this->data['email'] = $this->input->post('email');

		foreach($config['validation'] as $v)
		{
			$this->form_validation->set_rules($v['field'], $v['label'], $v['rules']);
		}

		if($this->form_validation->run() === FALSE)
		{
			$this->session->set_flashdata('validation_errors', validation_errors());
			redirect('/' . $config['redirect']['error'] );
			return false;
		}
		

		$this->load->model('BaseModel');

		$auth = $this->BaseModel->execute($config['argument'])->row();
		if( $auth==null )
		{
			$this->session->set_flashdata('validation_errors', 'Email tidak terdaftar');
			redirect('/' . $config['redirect']['error'] );
			return false;
		}
		$field['email'] = $auth->email; 
		
		if($auth->forgotten_password_code == null || $auth->forgotten_password_time < date('Y-m-d H:i:s') )
		{
			
			$field['forgotten_password_code'] = password_hash($auth->email, PASSWORD_BCRYPT, ['cost' => 12]);
			$field['forgotten_password_time'] = date('Y-m-d H:i:s', strtotime('+1hours') );
			
			$this->BaseModel->update('user', $field, ['email'=>$auth->email]);
			
			if( $mail->send($field, $config['sendMail']) )
			{
				$this->session->set_flashdata('flash', $field['forgotten_password_time']);
				$this->session->set_flashdata('validation_errors', 'Login Berhasil');
				redirect('/' . $config['redirect']['success']);
				return false;
			}
			
			$this->session->set_flashdata('validation_errors', 'Email gagal dikirim');
			$this->session->set_flashdata('email', $field['email']);
			redirect('/' . $config['redirect']['error'] );
			return false;
			
		}
			
		$field['forgotten_password_code'] = $auth->forgotten_password_code;
		$field['forgotten_password_time'] = date('Y-m-d H:i:s', strtotime('+1hours') );
		
		$this->BaseModel->update('user', $field, ['email'=>$auth->email]);
		if( ! $mail->send($field, $config['sendMail']) )
		{
			$this->session->set_flashdata('validation_errors', 'Email gagal dikirim');
			redirect('/' . $config['redirect']['error'] );
			return false;
		}
		
		$this->session->set_flashdata('flash', $field['forgotten_password_time']);
		$this->session->set_flashdata('email', $field['email']);
		$this->session->set_flashdata('validation_errors', 'Login Berhasil');
		redirect('/' . $config['redirect']['success']);
	}

	function signup()
	{
		$this->load->library('form_validation');

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('signup');

		foreach($config['validation'] as $v)
		{
			$this->form_validation->set_rules($v['field'], $v['label'], $v['rules']);
		}

		if($this->form_validation->run() === FALSE)
		{
			$post = [];
			foreach($config['post'] as $p)
			{
				$post[$p] = $this->input->post($p);
			}
			$this->session->set_flashdata('set_val', (object) $post);
			
			$this->session->set_flashdata('validation_errors', validation_errors());
			redirect('/' . $config['redirect']['error'] . '/' .$id);
			return false;
		}

		$this->load->model('BaseModel');

		$table = $config['insert']['table'];
		foreach($config['insert']['field'] as $f)
		{
			$field[$f] = $this->input->post($f);
		}
		if(isset($field['password']))
		{
			$field['password'] = password_hash( $field['password'],  PASSWORD_BCRYPT, ['cost'=>12]);	
		}
		$id = $this->BaseModel->insert($config['insert']['table'], $field);
		if($id)
		{
			$role = [
				'user_id' => $id,
				'role_id' => 3,
			];
			$this->BaseModel->insert('user_role', $role);
			$this->session->set_userdata($post, $id);
			redirect('/' . $config['redirect']['success'] . '/' .$id);
		}
		else
		{
			$this->session->set_flashdata('validation_errors', 'Data gagal disimpan');
			redirect('/' . $config['redirect']['error'] );
		}		
				
	}

	function confirmation()
	{

		$path = str_replace('_','/',$this->uri->segments[3]);
		$this->load->config($this->uri->segments[1].'/'.$path);
		$config = $this->config->item('confirmation');
		$this->load->library('confirmation');
		
		$confirm = new Confirmation;
		$confirm->setFormat($config['html']);
		$confirm->setFlow($this->config->item('flow'));
		$confirm->render();

	}

	function upload()
	{

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('upload');

		$this->load->library('upload', $config['upload']);

		$this->load->helper(['file']);
        
        if ( ! $this->upload->do_upload($config['file'])) 
        {
            $this->session->set_flashdata('validation_errors', $this->upload->display_errors());
			redirect('/' . $config['redirect']['error'] . '/' .$id);
			return false;
        }
		
		$path = $config['upload']['upload_path'];
		$content = read_file($path. $this->upload->file_name);
		
		if($config['upload']['allowed_types'] != 'jpg|png')
		{   
			$file = $path . $this->session->user_id . '.dat';			
			write_file($file,gzcompress($content));
			unlink($path . $this->upload->file_name);  
		}
		else
		{
			
			$upload_data = $this->upload->data();

			$config['image_library'] = 'gd2';
			$config['source_image'] = $upload_data['full_path'];
			$config['maintain_ratio'] = TRUE;
			$config['width']         = 200;
			$config['height']       = 200;

			$this->load->library('image_lib', $config);

			$this->image_lib->resize();
			if(file_exists($path . $this->session->user_id . '.png'))
			{
				unlink($path . $this->session->user_id . '.png');
			}
			rename($upload_data['full_path'],$path . $this->session->user_id . '.png');
        }		
		redirect('/' . $config['redirect']['success']);
	}

	function capture()
	{

		$path = str_replace('_','/',$this->uri->segments[2]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('capture');

		$this->load->helper(['file']);

		$capture = str_replace('data:image/png;base64,','',$this->input->post($this->uri->segments[3]));

		$file = $config['upload']['upload_path'] . $this->session->user_id . '.png';

		//echo '<img src="'.$this->input->post($this->uri->segments[3]).'">'; 
		//echo '<img src="data:image/png;base64,'.$capture.'">'; 
		if(base64_decode($capture) == '')
		{
			$this->session->set_flashdata('validation_errors', 'Foto belum diambil');
			redirect('/' . $config['redirect']['error']);	
			return fasle;
		}
		write_file($file,base64_decode($capture));
		redirect('/' . $config['redirect']['success']);
	}

	function download()
	{
		$path = str_replace('_','/',$this->uri->segments[3]);
		$this->load->library('encryption');
		$this->load->helper('file');

		$target =  $this->encryption->decrypt(urldecode($this->uri->segments[4]));

		if(! $target)
		{
			?>
			<h3>Halaman Download </h3>
			<div class="alert alert-warning"><strong>Warning!</strong> Tautan yang anda gunakan sudah kadaluwarsa</div>
			<?php
			return false;
		}
		$name = str_replace('/', '_', $target);
		
		$content = read_file(APPPATH . 'writeable/'.$target.'.dat');
		
		$this->output->set_header('HTTP/1.0 200 OK')
			->set_header('HTTP/1.1 200 OK')
			->set_header('Cache-Control: no-store, no-cache, must-revalidate')
			->set_header('Cache-Control: pos t-check=0, pre-check=0')
			->set_header('Pragma: no-cache')
			->set_content_type('application/docx')
			->set_header('Content-Disposition: inline; filename="'.$name.'.docx"')
		    ->set_output(gzuncompress($content));
	}

	function image()
	{
		$this->load->library('encryption');
		$target =  $this->encryption->decrypt(urldecode($this->uri->segments[3]));
		$path = APPPATH . 'writeable/'.$target.'.png';
		$img = imagecreatefrompng($path);
		$file = $path . $this->session->user_id . '.dat';			
		
		list($width, $height) = getimagesize($path);
		$newwidth = 500; 
		$newheight = $newwidth*$height/$width;

		$thumb = imagecreatetruecolor($newwidth, $newheight);
		imagecopyresized($thumb, $img, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

		header('Content-Type: image/png');
		imagepng($thumb);
		imagedestroy($thumb);
	}

	function pdf()
	{

		$path = str_replace('_','/',$this->uri->segments[3]);
		
		$this->load->config($this->uri->segments[1].'/'.$path);
		
		$config = $this->config->item('report2column');
		$this->load->helper('file');

		$content = read_file(APPPATH .'template/'.$path.'.tpl');
		
		$this->load->model('BaseModel');
		$data = $this->BaseModel->execute($config['argument'])->row();
		
		$replacement = explode('#', $data->data);
		echo vsprintf($content, $replacement);
	}
}

