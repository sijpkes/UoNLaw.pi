<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
 * IÕm pleased to hear it. If anyone asks you how the software differs from SPARK, the main differences are:
-        students donÕt evaluate themselves, only the other members of their group.
-        Students canÕt give every other student in their group the same mark (or at least they wonÕt be able 
 * 		to if you set it up in a way which prevents them from giving any two members the same number of points).
 * 
 * 
 */
$plugin_info = array(
    'pi_name'         => 'Pool grouped assessment plugin',
    'pi_version'      => '1.0',
    'pi_author'       => 'Paul Sijpkes',
    'pi_author_url'   => '',
    'pi_description'  => 'Provides tags for group assessment features.',
    'pi_usage'        => Law::usage()
);

class Law {
	
	public $return_data = "";
	private $segment_count = 0;
	private $segment_prefix = "";
	private $assessor_student_no = "";
	private $assessor_group_name = "";
	private $assessor_id = 0;
	private $save_message = "";
	private $member_group_id = 0;
	private $member_access_group_id = 0;
	private $access_key = ""; // set password here
	private $access_uri = "";
	private $home_link = "<p class='home'><a href=\"/index.php/law/console\">Home</a></p>";
	
    public function __construct()
    {	
		if (session_id() == '')
		{
		   session_start();
		}
		ini_set("auto_detect_line_endings", TRUE);
		
		$this->segment_count = count(ee()->uri->segment_array());
		$this->segment_prefix = "/".ee()->uri->segment($this->segment_count-1).'/'.ee()->uri->segment($this->segment_count);
		
		$this->member_group_id = isset($_SESSION['law_group_id']) ? $_SESSION['law_group_id'] : 0;
		$this->member_access_group_id = ee()->TMPL->fetch_param('group_access_id');
		
		if(empty($this->member_access_group_id)) {
		$this->assessor_student_no = ee()->input->post('student_no');
		
		$results = ee()->db->query("SELECT id, group_name FROM exp_law_groups WHERE student_no = '$this->assessor_student_no' LIMIT 1");
		if ($results->num_rows() > 0)
		{
			foreach($results->result_array() as $row)
		    {
				$this->assessor_id = $row['id'];
				$this->assessor_group_name = $row['group_name'];
			}
		} 
		}
    }

	public function login() {
		$form = "";
		$this->access_uri = ee()->TMPL->fetch_param('access_uri');
		
		$message = "";
		if(isset($_POST['password'])) {
			if($_POST['password'] === $this->access_key) {
				$_SESSION['law_group_id'] = $this->member_access_group_id;
				if($this->access_uri === 'confirmation') { // confirm an action between the tags	
					return ee()->TMPL->tagdata;
				} else {
					ee()->functions->redirect($this->access_uri);
					return;
				}
			} else {
				$message = "Wrong password, please try again.";
				$_SESSION['law_group_id'] = "";
			}
		}
		
		ee()->load->helper('form');
		$form .= form_open($this->segment_prefix);
		$pdata = array('name' => 'password', 'id' => 'password', 'value' => null, 'max-length' => '50', 'size' => '10');
		$form .= form_password('password', 'Password');
		$form .= form_submit('login',  'Login');
		if(!empty($message)) $form .= "<p class='errorMsg'>$message</p>";
		$form .= form_close();
		$form .= $this->home_link;
		return $form;
	}
	
	public function logout() {
		$_SESSION['law_group_id'] = "";
		return ee()->TMPL->tagdata;
	}

	public function logged_in() {
			$login_uri = ee()->TMPL->fetch_param('login_uri');
		if($_SESSION['law_group_id'] == $this->member_access_group_id) {
			return ee()->TMPL->tagdata;
		} else {
			ee()->functions->redirect($login_uri);
		}
	}

	public function uploadform() {
		$login_uri = ee()->TMPL->fetch_param('login_uri');
		if($this->member_access_group_id !== $this->member_group_id && $this->member_group_id != 1) ee()->functions->redirect($login_uri);
		$form = "";
		
		if(end(ee()->uri->segment_array()) == 'upload') {
			$config['upload_path'] = '/home/u451587284/public_html/law_uploads';
			$config['allowed_types'] = 'csv';
			$config['max_size']	= '5242880';
			
			ee()->load->library('upload', $config);

			if ( ! ee()->upload->do_upload())
			{
				$form .= ee()->upload->display_errors();
			}
			else
			{
				$form .= "<h1>Upload Successful</h1>";
				$file_data = ee()->upload->data();
				$handle = fopen($file_data['full_path'], "r");
				$i = 0;
				
				$csv = array();
				while (($nextline = fgetcsv($handle)) !== FALSE) {
					if($i++ > 0) {	
						$csv[] = $nextline;
					}
				}
				
				$affr = 0;
				foreach($csv as $row) {
					$data = array('student_no' => $row[0], 'full_student_name' => $row[1], 'group_no' => $row[2], 'group_name' => $row[3]);
					$sql = ee()->db->insert_string('law_groups', $data);
					ee()->db->query($sql);
					$affr += ee()->db->affected_rows();
				}
				$form .=  "<h2>".$affr." rows were inserted into the student list.</h2>";
				unlink($file_data['full_path']);
			}
		
		} else {
	
		ee()->load->helper('form');
		
		$form .= "<em>Columns must be in the order of: Student No, Full Name, Group No., Group Name</em></br>";
		$form .=  form_open_multipart($this->segment_prefix.'/upload');
		$form .= form_upload('userfile', 'userfile');
		$form .= form_submit("upload", "Upload");
		$form .= form_close();
		}
	
		$form .= $this->home_link;
		return $form;
	}

	public function assess() {
		$form = "";
		
		$action = ee()->input->post('assess_action');
	
		ee()->load->helper('form');
		
		if(strlen($this->assessor_student_no) === 0) {
			$form .= "<strong>Please enter your student number:</strong></br>";	
			$form .=  form_open($this->segment_prefix);
			$data = array(
		              'name'        => 'student_no',
		              'id'          => 'student_no',
		              'value'       => '0000000',
		              'maxlength'   => '7',
		              'size'        => '9',
		              'style'       => 'width: 8em',
		            );
					$form .= form_input($data);
					$form .= form_hidden('assess_action', 'retrieve');
					$form .= form_submit("submit", "Load my group");
					$form .= form_close();	
		}			
		else if($action === 'assign-marks') {
							$post_data = array();
							$total = 0;
							foreach($_POST as $key => $value) 
							{
								$fullkey = explode("_", $key);
								$data_type = $fullkey[0];

								if($data_type === 'score') {
										$student_id = $fullkey[1];
										$post_data[$student_id]['score'] = $value;
										$total += $value;
								}
								if($data_type === 'comment') {
									$student_id = $fullkey[1];
									$post_data[$student_id]['comment'] = mysql_real_escape_string($value);
								}
							}
							if($_POST['locked'] == 1 && $total !== 100) {
								$form .= "<h1 class='errormsg'>There was an error submitting your assessment.</h1>";
								$form .= "<p>Your total score was $total points. <a href='/index.php/law/assess-peers'>Go back</a></p>";
								if($this->member_group_id == 6) $form .= $this->home_link;
								return $form;
							}	
							$total_affected = 0;
							
							foreach($post_data as $student_id => $row) {
								$sql = <<<mysql
								INSERT INTO `exp_law_assessments` (`assessor_id`, `student_id`, `score`, `comment`, `locked`) 
								VALUES ('$this->assessor_id', '$student_id', '$row[score]', '{$row[comment]}', '$_POST[locked]') 
								ON DUPLICATE KEY UPDATE `assessor_id` = '$this->assessor_id',  `student_id` = '$student_id',
								`score` = '{$row[score]}', `comment` = '{$row[comment]}', `locked` = '{$_POST[locked]}';
mysql;
								$total_affected += ee()->db->query($sql);
							}	
									
							if($_POST['locked'] == 1) {
								if($total_affected > 0) 
									$form .= "<h1>Your assessment has been submitted successfully.</h1>";
								else 
									$form .= "<h1 class='errormsg'>There was an error submitting your assessment.</h1>";
							} 
							if($_POST['locked'] == 0) { 
								if($total_affected > 0) {
									$this->save_message = "<span class='saveMsg'> Saved </span>";
								} else { 
									$this->save_message = "<span class='saveErrorMsg'>There was a network issue saving, try refreshing and check your connection.</span>";	
									}
							}
					} 	
		if($action === 'retrieve' || ($action === 'assign-marks' && $_POST['locked'] == 0)) {
		$form .= "<h1>$this->assessor_group_name</h1>";
		$form .= "<p id='student-no'>Student number: $this->assessor_student_no</p>";
		$results = ee()->db->query("SELECT g.id, g.full_student_name, g.group_no, g.group_name, a.score, a.comment, a.locked FROM exp_law_groups g LEFT JOIN exp_law_assessments a ON g.id = a.student_id AND a.assessor_id = '$this->assessor_id' WHERE g.group_no IN (SELECT group_no FROM exp_law_groups WHERE student_no = '$this->assessor_student_no') AND g.student_no <> '$this->assessor_student_no' LIMIT 10");

		if ($results->num_rows() > 0)
		{	
			$attributes = array('id' => 'assessments');
			$form .= ee()->TMPL->tagdata;
			$form .=  form_open($this->segment_prefix, $attributes);
			$form .= form_hidden('student_no', $this->assessor_student_no);
			$form .= form_hidden('assess_action', 'assign-marks');
			$form .= form_hidden('locked', '0');
		    $table = "<table class='pa-table'>";
			$lock_count = 0;
			$table .= "<tr><th>Name</th><th>Score</th><th>Comment</th></tr>";
			
			foreach($results->result_array() as $row)
			{
				
				if($row['locked'] == 0) {
					$table .= "<tr><td>$row[full_student_name]</td><td><input type='text' name='score_$row[id]' class='student_assess' id='score_$row[id]' value='$row[score]'/>
					<td><textarea id='comment_$row[id]' class='comment' name='comment_$row[id]'>$row[comment]</textarea></td>";
				} else {
					$lock_count++;
			}
			}
			if($lock_count > 0) {
				$form .= "<p class='okmsg'><strong>You have already assessed the other students in your group.</strong></p>"; 
				if($this->member_group_id == 6) $form .= $this->home_link;	
				return $form;
			}
			$table .= "</table>";
			
			$form .= "$table\n<button id='Save' title='Save marks and return to edit later.'>Save</button> $this->save_message";
			$form .= "<button id='assess'>Submit Assessment</button>";
			$form .= "<p><em>Note that your scores and comments will not be recorded until they have been submitted. Saving allows you to return to the form later but it does not submit your assessment.</em></p>";
			$form .= form_close();
			$form .= $this->outputJavascript();
		} else {
			$form .= "<p>This student number is not available. Please check and try again.</p>";
		}	
		
		} 
		
	if($this->member_group_id == 6) $form .= $this->home_link;	
	
	return $form;
	}
	
	public function download() {
		$login_uri = ee()->TMPL->fetch_param('login_uri');
	
		if($this->member_access_group_id !== $this->member_group_id && $this->member_group_id != 1) ee()->functions->redirect($login_uri);
		$form = "";
				
		$results = ee()->db->query("SELECT * FROM exp_law_groups g, exp_law_assessments a WHERE g.id = a.student_id AND a.locked = '1'");
		
		$totals = array();
		$group_counts = array();
		foreach($results->result_array() as $row)
		{
			if(!array_key_exists($row['group_no'], $group_counts)) {
				$count_result = ee()->db->query("SELECT count(*) member_count FROM exp_law_groups WHERE group_no = '$row[group_no]'");
				//$form .= var_export($count_result);
				foreach($count_result->result_array() as $count_row) {
					$group_counts[$row['group_no']] = (int)$count_row['member_count'];
				}
			}
				
				if(array_key_exists($row['id'], $totals)) {
					$tscore = ((int) $row['score']) + ((int) $totals[$row['id']][4]);	
					//if(isset($tscore)) {
						$totals[$row['id']][4] = $tscore;
						$totals[$row['id']][5] = $tscore / 100;
						$totals[$row['id']][7] += 1; // number of group members that have assessed this student
					//}
				} else {					
					$totals[$row['id']] = array($row['full_student_name'], $row['student_no'],
								$row['group_no'], $row['group_name'], $row['score'],
								empty($row['score']) ? 0 : $row['score'] / 100,  $group_counts[$row['group_no']], 1);
				}
		}
		
		//$form .= "<h3>Test Output</h3>";
		//$form .= "<p>";
		
		//$form .= "</p>";			
		ee()->load->helper('download');
		$file = tempnam("/law-uploads", "tmp_");
		$handle = fopen($file, "w+b");
		fputcsv($handle, array("Full Name", "Student No", "Group No", "Group Name", "Total Score", "Multiplier", "No of Group Members", "No Assessed this Student"));
		foreach($totals as $line) {
			fputcsv($handle,$line);
		}
		$data = file_get_contents($file);

		force_download("assessment.csv", $data);
		unlink($file);
		
		return $form;
	}
	
	public function download_comments() {
			$login_uri = ee()->TMPL->fetch_param('login_uri');

			if($this->member_access_group_id !== $this->member_group_id && $this->member_group_id != 1) ee()->functions->redirect($login_uri);
			$form = "";

			$results = ee()->db->query("SELECT g.id, a.assessor_id, g.full_student_name, g.student_no, a.comment FROM exp_law_groups g
						LEFT JOIN (exp_law_assessments a) ON ( g.id = a.student_id ) WHERE a.locked =  '1'"); 
			
				$comments = array();
				foreach($results->result_array() as $row)
				{
					if(!empty($row['comment'])) {
						if(array_key_exists($row['id'], $comments)) {
							$comments[$row['id']][] = $row['comment'];
						} else {					
							$comments[$row['id']] = array($row['student_no'], $row['full_student_name'], $row['comment']);
						}
					}
				}
				//print_r($comments);
				
					ee()->load->helper('download');
					$file = tempnam("/law-uploads", "tmp_");
					$handle = fopen($file, "w+b");
					
					$headers = 0;
					$max_headers = 0;
					foreach($comments as $array) {
						$headers = count($array);
						$max_headers = $headers > $max_headers ? $headers : $max_headers;
					}
					
					$header_array = array("Student No", "Full Name");
					for($i=0; $i < $max_headers; $i++) {
						$label = $i+1;
						$header_array[] = "Comment $label";
					}
					
					fputcsv($handle, $header_array);
					foreach($comments as $line) {
						fputcsv($handle,$line);
					}
					$data = file_get_contents($file);

					force_download("comments.csv", $data);
					unlink($file);
	}
	
	public function clear_assessments() {
		$affected = ee()->db->query("TRUNCATE TABLE exp_law_assessments");
		
		if(!empty($affected)) {
			return "<p>All assessments have been cleared from the database.</p>".$this->home_link;;
		} else {
			return "<p class='errorMsg'>Error updating database.</p>".$this->home_link;;
		}
	}
	
	public function clear_groups() {
		$affected = ee()->db->query("TRUNCATE TABLE exp_law_groups");
		
		if(!empty($affected)) {
			return "<p>All groups have been cleared from the database.</p>".$this->home_link;
		} else {
			return "<p class='errorMsg'>Error updating database.<p class='errorMsg'>".$this->home_link;
		}
	}
	
	public function unlock_student_accounts() {
		/* not implemented or yet TESTED !!! */
		
		$form .= ee()->TMPL->tagdata;
		$form .= form_open();
		$form .= "<p>List the student numbers to unlock, one line at a time.</p>";
		$form .= "<textarea name='student_numbers'>$_POST[student_numbers]</textarea>";
		$form .= form_submit("submit", "Load my group");
		$form .= form_close();	 
		
		if(isset($_POST['student_numbers'])) {
		
		$student_numbers = explode('\n' , $_POST['student_numbers']);
		$student_numbers = implode(',' , $student_numbers);
		
		$affected = ee()->db->query("UPDATE `exp_law_assessments` LEFT JOIN `exp_law_groups`
					     ON (assessor_id = id) SET `locked` = '0'
					     WHERE student_no IN ($student_numbers)");
		
			if(!empty($affected)) {
				return "<p>$affected student accounts were unlocked.</p>".$this->home_link;
			} else {
				return "<p class='errorMsg'>Error updating database.<p class='errorMsg'>".$this->home_link;
			}
		}
	return $form;
	}
	
	protected static function outputJavascript() {
		ob_start();
			include("main.js");
		$str = ob_get_contents();
		ob_end_clean();
		
		return "<script>$str</script>";
	}
	
	 public static function usage()
	    {
	        ob_start();  ?>

			Provides features for uploading student CSV files and pooled group assessment
	   		{exp:law:uploadform}

	    <?php
	        $buffer = ob_get_contents();
	        ob_end_clean();

	        return $buffer;
	    }
}
