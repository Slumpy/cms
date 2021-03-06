<?php defined('SYSPATH') OR die('No direct script access.');
/**
 * Media Controller
 *
 * @package    Gleez\Media\Controller
 * @author     Sandeep Sangamreddi - Gleez
 * @version    1.0.0
 * @copyright  (c) 2011-2013 Gleez Technologies
 * @license    http://gleezcms.org/license Gleez CMS License 
 */
class Controller_Media extends Controller {
	
	/**
	 * The configuration settings
	 * @var Config
	 */
	public $config;
	
	/**
	 * The before() method is called before controller action
	 *
	 * @uses  Request::param
	 * @uses  Theme::set_theme
	 * @uses  Config::load
	 */
	public function before()
	{
		if ($theme = $this->request->param('theme', FALSE))
		{
			Theme::set_theme($theme);
		}
		
		parent::before();
		
		// Load config
		$this->config = Kohana::$config->load('media');
	}
	
	/**
	 * Static file serving (CSS, JS, images, etc.)
	 *
	 * @uses  Request::param
	 * @uses  Request::uri
	 * @uses  Kohana::find_file
	 * @uses  Response::check_cache
	 * @uses  Response::body
	 * @uses  Response::headers
	 * @uses  Response::status
	 * @uses  File::mime_by_ext
	 * @uses  Config::get
	 * @uses  Log::add
	 * @uses  System::mkdir
	 */
	public function action_serve()
	{
		// Get file theme from the request
		$theme = $this->request->param('theme', FALSE);

		// Get the file path from the request
		$file = $this->request->param('file');
		
		// Find the file extension
		$ext = pathinfo($file, PATHINFO_EXTENSION);
		
		// Remove the extension from the filename
		$file = substr($file, 0, -(strlen($ext) + 1));
		
		if ($file_name = Kohana::find_file('media', $file, $ext))
		{
			// Check if the browser sent an "if-none-match: <etag>" header, and tell if the file hasn't changed
			$this->response->check_cache(sha1($this->request->uri()) . filemtime($file_name), $this->request);
			
			// Send the file content as the response
			$this->response->body(file_get_contents($file_name));
			
			// Set the proper headers to allow caching
			$this->response->headers('content-type', File::mime_by_ext($ext));
			$this->response->headers('last-modified', date('r', filemtime($file_name)));

			// This is ignored by check_cache
			$this->response->headers('cache-control', 'public, max-age=2592000');
			
			if ($this->config->get('cache', FALSE))
			{
				// Set base path
				$path = $this->config->get('public_dir', 'media');
			
				// Override path if we're in admin
				if ($theme)
				{
					$path = $path.DIRECTORY_SEPARATOR . $theme;
				}
				
				// Save the contents to the public directory for future requests
				$public_path = $path.DIRECTORY_SEPARATOR. $file . '.' . $ext;
				$directory   = dirname($public_path);
				
				if ( ! is_dir($directory))
				{
					// Recursively create the directories needed for the file
					System::mkdir($directory, 0777, TRUE);
				}
				
				file_put_contents($public_path, $this->response->body());
			}
		}
		else
		{
			Kohana::$log->add(LOG::ERROR, 'Media controller error while loading file: `:file`',
				array(':file' => $file)
			);

			// Return a 404 status
			$this->response->status(404);
		}
	}
	
}
