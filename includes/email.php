<?php
if ( !defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( !class_exists( "WCWH_Email" ) )
{

class WCWH_Email
{
	protected $_args;
	public $id;
	public $template_html;
	public $subject;
	public $message;
	public $recipient;
	public $attachment;

	public $find;
	public $replace;

	public $section;
	protected $datas;
	protected $ref_id;

	protected $log_id;
	
	public function __construct() 
	{
		$this->id               = 'wcwh_email';
		$this->title            = 'Email Notification';
		$this->description      = 'An email notification to user.';
		$this->template_html    = "default.php";
		$this->subject          = 'Email Notification';
		$this->message			= '';
		$this->recipient		= '';

		$this->section 			= '';
		
		//set email
		add_action( 'wcwh_set_email', array( $this, 'set_email' ), 10, 1 );

		// Trigger to send this email
		add_action( 'wcwh_trigger_email', array( $this, 'trigger' ), 10, 1 );

		//wp mail error
		add_action('wp_mail_failed', array( $this, 'mail_error' ), 10, 1 );
	}

	public function __destruct()
	{
		
	}

	public function set_email_datas( $data )
	{
		$this->datas = $data;
	}
	public function set_ref_id( $id )
	{
		$this->ref_id = $id;
	}

	public function get_email_datas()
	{
		return $this->datas;
	}
	public function get_ref_id()
	{
		return $this->ref_id;
	}
	
	public function set_email( $args )
	{
		$this->_args = $args;

		$this->id 			= !empty( $args['id'] )? $args['id'] : $this->id;
		$this->template_html= !empty( $args['template_html'] )? $args['template_html'] : $this->template_html;
		$this->subject		= !empty( $args['subject'] )? $args['subject'] : $this->subject;
		$this->message		= !empty( $args['message'] )? $args['message'] : $this->message;
		$this->recipient	= !empty( $args['recipient'] )? $args['recipient'] : $this->recipient;
		$this->attachment 	= !empty( $args['attachment'] )? $args['attachment'] : $this->attachment;
		
		$this->section 		= !empty( $args['section'] )? $args['section'] : $this->section;
		$this->datas 		= !empty( $args['datas'] )? $args['datas'] : $this->datas;
		$this->ref_id 		= !empty( $args['ref_id'] )? $args['ref_id'] : $this->ref_id;
	}
	
	public function trigger( $args = [] ) 
	{
		$succ = true;
		$wc_emails = WC_Emails::instance();

		$attachment = apply_filters( 'wcwh_{$this->section}_email_attachment', $this->attachment, $this->datas, $this->ref_id );
		
		$recipient = $this->recipient;
		if( ! empty( $recipient ) )
		{
			$succ = wp_mail( $this->get_recipient( $recipient ), $this->get_subject(), $this->get_content(), $this->get_headers(), $attachment );
			if( ! $succ ) $error = "Mailer function failed";
		}
		else 
		{
			$succ = false;
			$error = "No Recipient";
		}
		
		//Log Activity
        $log_id = 0;
        $mail_info = [
            'mail_id'		=> $this->id,
            'section'       => ( $this->section_id )? $this->section_id : '',
            'ref_id'		=> ( $this->ref_id )? $this->ref_id : 0,
            'args'			=> $this->_args,
            'ip_address'    => apply_filters( 'wcwh_get_user_ip', 1 ),
            'status'        => ( $succ )? 1 : 0,
        ];
        if( ! empty( $error ) ) $mail_info['error_remark'] = $error;
        //$log_id = apply_filters( 'wcwh_log_mail', 'save', $mail_info );
        $log_id = $this->log_mail( 'save', $mail_info );
		if( $log_id ) $this->log_id = $log_id;
		
		return $succ;
	}
	
	public function log_mail( $action = 'save', $datas = array() )
	{	
		if( ! $datas ) return false;

		if ( !class_exists( "WCWH_MailLog_Controller" ) ) include_once( WCWH_DIR . "/includes/controller/mailLogCtrl.php" );

		$Inst = new WCWH_MailLog_Controller();

		$result = $Inst->action_handler( $action, $datas, $datas );
		if( $result['succ'] )
			return $result['id'];

		return false;
	}

	public function mail_error( $wp_error )
	{
		if( $this->log_id > 0 )
		{
			$log_id = apply_filters( 'wcwh_log_activity', 'update', [
                'id'            => $this->log_id,
                'status'        => 0,
                'error_remark'  => $wp_error->get_error_message(),
            ] );
		}
	}
	
	public function get_recipient( $recipient = '' ) 
	{
		if( ! $recipient ) return false;
		
		$recipients = array_map( 'trim', explode( ',', $recipient ) );
		$recipients = array_filter( $recipients, 'is_email' );

		return implode( ', ', $recipients );
	}
	
	public function get_subject() 
	{
		return $this->format_string( $this->subject );
	}
	
	public function get_content() 
	{
		$email_content = $this->style_inline( $this->get_content_html() );

		return wordwrap( $email_content, 75 );
	}
	
	public function get_headers() 
	{
		return "Content-Type: text/html\r\n";
	}
	
	public function get_content_html() 
	{
		$args = [
			'section' => $this->section,
			'ref_id' => $this->ref_id,
			'message' => $this->format_string( $this->message ),
		];
		ob_start();

			do_action( 'wcwh_get_template', 'email/'.$this->template_html, $args );
		
		return ob_get_clean();
	}
	
	public function style_inline( $content ) 
	{
		// make sure we only inline CSS for html emails
		ob_start();
			do_action( 'wcwh_get_template', 'email/email-styles.php' );
		$css = apply_filters( 'wcwh_{$this->section}_email_styles', ob_get_clean() );

		$emogrifier_class = 'Pelago\\Emogrifier';

		if( class_exists( 'DOMDocument' ) && class_exists( $emogrifier_class ) ) 
		{
			try 
			{
				$emogrifier = new $emogrifier_class( $content, $css );

				do_action( 'woocommerce_emogrifier', $emogrifier, $this );

				$content    = $emogrifier->emogrify();
				$html_prune = \Pelago\Emogrifier\HtmlProcessor\HtmlPruner::fromHtml( $content );
				$html_prune->removeElementsWithDisplayNone();
				$content    = $html_prune->render();
			} 
			catch ( Exception $e ) 
			{
				$e->getMessage();
			}
		} 
		else 
		{
			$content = '<style type="text/css">' . $css . '</style>' . $content;
		}
		
		return $content;
	}
	
	public function format_string( $string ) 
	{
		if( !empty( $this->find ) && !empty( $this->replace ) )
			return str_replace( $this->find, $this->replace, $string );
		else
			return $string;
	}
}

new WCWH_Email();

}