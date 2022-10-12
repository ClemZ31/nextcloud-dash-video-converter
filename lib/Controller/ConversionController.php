<?php

namespace OCA\Video_Converter\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;
use \OCP\IConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OC\Files\Filesystem;


class ConversionController extends Controller
{

	private $userId;

	/**
	 * @NoAdminRequired
	 */
	public function __construct($AppName, IRequest $request, $UserId)
	{
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
	}

	public function getFile($directory, $fileName)
	{
		\OC_Util::tearDownFS();
		\OC_Util::setupFS($this->userId);
		return Filesystem::getLocalFile($directory . '/' . $fileName);
	}
	/**
	 * @NoAdminRequired
	 */
	public function convertHere($nameOfFile, $directory, $external, $type, $preset, $priority, $movflags = false, $codec = null, $vbitrate = null, $scale = null, $shareOwner = null, $mtime = 0)
	{
		$file = $this->getFile($directory, $nameOfFile);
		$dir = dirname($file);
		$response = array();
		if (file_exists($file)) {
			$cmd = $this->createCmd($file, $preset, $type, $priority, $movflags, $codec, $vbitrate, $scale);			
			exec($cmd, $output, $return);
			// if the file is in external storage, and also check if encryption is enabled
			if ($external || \OC::$server->getEncryptionManager()->isEnabled()) {
				//put the temporary file in the external storage
				Filesystem::file_put_contents($directory . '/' . pathinfo($nameOfFile)['filename'] . "." . $type, file_get_contents(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $type));
				// check that the temporary file is not the same as the new file
				if (Filesystem::getLocalFile($directory . '/' . pathinfo($nameOfFile)['filename'] . "." . $type) != dirname($file) . '/' . pathinfo($file)['filename'] . "." . $type) {
					unlink(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $type);
				}
			} else {
				//create the new file in the NC filesystem
				Filesystem::touch($directory . '/' . pathinfo($file)['filename'] . "." . $type);
			}
			//if ffmpeg is throwing an error
			if ($return == 127) {
				$response = array_merge($response, array("code" => 0, "desc" => "ffmpeg is not installed or available \n
				DEBUG(" . $return . "): " . $file . ' - ' . $output));
				return json_encode($response);
			} else {
				$response = array_merge($response, array("code" => 1, "desc" => "Convertion OK: " . $cmd));

				// After file is converted, we need to re-scan files in directory				
				exec("php /var/www/nextcloud/occ files:scan --all"); //prod
				//exec("/Applications/MAMP/bin/php/php7.4.12/bin/php /Applications/MAMP/htdocs/nextcloud/occ files:scan --all"); //dev


				return json_encode($response);
			}
		} else {
			$response = array_merge($response, array("code" => 0, "desc" => "Can't find file at " . $file));
			return json_encode($response);
		}
	}
	/**
	 * @NoAdminRequired
	 */
	public function createCmd($file, $preset, $output, $priority, $movflags, $codec, $vbitrate, $scale)
	{
		$middleArgs = "";
		if ($output == "webm") {
			switch ($preset) {
				case 'faster':
					$middleArgs = "-vcodec libvpx -cpu-used 1 -threads 16";
					break;
				case 'veryfast':
					$middleArgs = "-vcodec libvpx -cpu-used 2 -threads 16";
					break;
				case 'superfast':
					$middleArgs = "-vcodec libvpx -cpu-used 4 -threads 16";
					break;
				case 'ultrafast':
					$middleArgs = "-vcodec libvpx -cpu-used 5 -threads 16 -deadline realtime";
					break;
				default:
					break;
			}
		} else {
			if ($codec != null) {
				switch ($codec) {
					case 'x264':
						$middleArgs = "-vcodec libx264 -preset " . escapeshellarg($preset) . " -strict -2";
						break;
					case 'x265':
						$middleArgs = "-vcodec libx265 -preset " . escapeshellarg($preset) . " -strict -2";
						break;
				}
			} else {
				$middleArgs = "-preset " . escapeshellarg($preset) . " -strict -2";
			}

			if ($movflags) {
				$middleArgs = $middleArgs . " -movflags +faststart ";
			}

			if ($vbitrate != null) {
				switch ($vbitrate) {
					case '1':
						$vbitrate = '1000k';
						break;
					case '2':
						$vbitrate = '2000k';
						break;
					case '3':
						$vbitrate = '3000k';
						break;
					case '4':
						$vbitrate = '4000k';
						break;
					case '5':
						$vbitrate = '5000k';
						break;
					case '6':
						$vbitrate = '6000k';
						break;
					case '7':
						$vbitrate = '7000k';
						break;
					default:
						$vbitrate = '2000k';
						break;
				}
				$middleArgs = $middleArgs . " -b:v " . $vbitrate;
			}

			if ($scale != null) {
				switch ($scale) {
					case 'vga':
						$scale = " -vf scale=640:480";
						break;
					case 'wxga':
						$scale = " -vf scale=1280:720";
						break;
					case 'hd':
						$scale = " -vf scale=1368:768";
						break;
					case 'fhd':
						$scale = " -vf scale=1920:1080";
						break;
					case 'uhd':
						$scale = " -vf scale=3840:2160";
						break;
					case '320':
						$scale = " -vf scale=-1:320";
						break;
					case '480':
						$scale = " -vf scale=-1:480";
						break;
					case '600':
						$scale = " -vf scale=-1:600";
						break;
					case '720':
						$scale = " -vf scale=-1:720";
						break;
					case '1080':
						$scale = " -vf scale=-1:1080";
						break;
					default:
						$scale = "";
						break;
				}
				$middleArgs = $middleArgs . $scale;
			}
		}
		//echo $link;
		// I put this here because the code up there seems to be chained in a string builder and I didn't want to disrupt the code too much.
		// This is useful if you just want to change containers types, and do no work with codecs. So you can convert an MKV to MP4 almost instantly.
		if ($codec == "copy") {
			$middleArgs = "-codec copy";
		}

		//$ffmepgPath = " /usr/local/bin/"; //uncomment for dev
		$ffmepgPath = " "; //uncomment for prod


		$subsInput = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . ".srt");
		$subsOutput = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . ".vtt");		
		$subTitlesConversionCmd = $ffmepgPath. "ffmpeg -i ". $subsInput." -f webvtt ". $subsOutput;		
		$refreshDirCmd = " php /var/www/nextcloud/occ files:scan --all";
		//die($subTitlesConversionCmd);

		// Reference for mpd						
		if ($output == "mpd") {
			// ffmpeg -re -y -i '/Users/danielfigueroa/Downloads/mdash/original.mp4' -preset slow -keyint_min 100 -g 100 -sc_threshold 0 -r 25 -c:v libx264 -pix_fmt yuv420p -c:a aac -c:s copy -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M  -map a:0 -b:a:0 128k -ac:a:0 1 -use_template 1 -hls_playlist 1 -use_timeline 1 -seg_duration 4  -media_seg_name 'source/chunk-stream$RepresentationID$-$Number%05d$.$ext$' -init_seg_name 'source/init-stream$RepresentationID$.$ext$' -f dash '/Users/danielfigueroa/Downloads/mdash/original.mpd'
			$currentTime = date("Ymdhis");
			$source_dir = dirname($file) . '/source' . $currentTime;
			// die("source_dir: ". $source_dir);
			mkdir($source_dir, 0700);
			$media_dir = "source" . $currentTime . "/chunk-stream\$RepresentationID\$-\$Number%05d\$.m4s";
			$segments_dir = "source" . $currentTime . "/init-stream\$RepresentationID\$.m4s";
			$output_mpd_file = escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $output);
			$master_pl_name = escapeshellarg(pathinfo($file)['filename'] . ".m3u8");			
			$cmd = $ffmepgPath . "ffmpeg -re -y -i " . escapeshellarg($file) . " -preset slow -keyint_min 100 -g 100 -sc_threshold 0 -r 25 -c:v libx264 -pix_fmt yuv420p -c:a aac -c:s copy -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M  -map a:0 -b:a:0 128k -ac:a:0 1 -use_template 1 -hls_playlist 1 -use_timeline 1 -seg_duration 4 -media_seg_name '" . $media_dir . "' -init_seg_name '" . $segments_dir . "'  -f dash " . $output_mpd_file;
			$cmd .= " && ". $subTitlesConversionCmd;			
			$cmd .= " && " . $refreshDirCmd;	
			//die($cmd);
		} elseif ($output == "m3u8") {
			// Reference for hls
			/*			
			ffmpeg -re -y -err_detect ignore_err -i 'original.mp4' -preset slow -keyint_min 100 -sc_threshold 0 -c:v libx264 -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M -map a:0 -c:a:0 aac -b:a:0 64k -ac 2 -map a:0 -c:a:1 aac -b:a:1 64k -ac 2 -map a:0 -c:a:2 aac -b:a:2 64k -ac 2 -map a:0 -c:a:3 aac -b:a:3 128K -ac 2 -map a:0 -c:a:4 aac -b:a:4 128K -ac 2 -map a:0 -c:a:5 aac -b:a:5 128K -ac 2 -f hls -strftime_mkdir 1 -hls_flags independent_segments+delete_segments -hls_segment_type mpegts -hls_segment_filename 'stream_%v/data%02d.ts'  -master_pl_name 'master.m3u8' -var_stream_map 'v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3 v:4,a:4 v:5,a:5' 'stream_%v.m3u8'
			*/
						
			$master_pl_name = pathinfo($file)['filename'] . "." . $output;			
			$hls_segment_filename = "stream_%v/data%02d.ts";
			$var_stream_map = "stream_%v.m3u8";
			$changeDirCmd = "cd ".escapeshellarg(dirname($file))." && ";												 
			$cmd = $changeDirCmd . $ffmepgPath . "ffmpeg -re -y -err_detect ignore_err -i " . escapeshellarg($file) . " -preset slow -keyint_min 100 -sc_threshold 0 -c:v libx264 -map v:0 -s:0 256x144 -b:v:0 160k -maxrate:0 160k -bufsize:0 320k -map v:0 -s:1 426x240 -b:v:1 400k -maxrate:1 400k -bufsize:1 800k -map v:0 -s:2 640x360 -b:v:2 700k -maxrate:2 700k -bufsize:2 1.4M -map v:0 -s:3 854x480 -b:v:3 1.25M -maxrate:3 1.25M -bufsize:3 2.5M -map v:0 -s:4 1280x720 -b:v:4 3.2M -maxrate:4 3.2M -bufsize:4 6.4M -map v:0 -s:5 1920x1080 -b:v:5 5.3M -maxrate:5 5.3M -bufsize:5 10.6M -map a:0 -c:a:0 aac -b:a:0 64k -ac 2 -map a:0 -c:a:1 aac -b:a:1 64k -ac 2 -map a:0 -c:a:2 aac -b:a:2 64k -ac 2 -map a:0 -c:a:3 aac -b:a:3 128K -ac 2 -map a:0 -c:a:4 aac -b:a:4 128K -ac 2 -map a:0 -c:a:5 aac -b:a:5 128K -ac 2 -f hls -strftime_mkdir 1 -hls_flags independent_segments+delete_segments -hls_segment_type mpegts -hls_segment_filename '" . $hls_segment_filename . "' -master_pl_name '" . $master_pl_name . "' -var_stream_map 'v:0,a:0 v:1,a:1 v:2,a:2 v:3,a:3 v:4,a:4 v:5,a:5' '". $var_stream_map."'";
			$cmd .= " && " . $subTitlesConversionCmd;
			$cmd .= " && " . $refreshDirCmd;			
			
			//echo $cmd;
			die($cmd);
		} else
			$cmd = $ffmepgPath . "ffmpeg -y -i " . escapeshellarg($file) . " " . $middleArgs . " " . escapeshellarg(dirname($file) . '/' . pathinfo($file)['filename'] . "." . $output);

		if ($priority != "0") {
			$cmd = "nice -n " . escapeshellarg($priority) . $cmd;
		}
		return $cmd;
	}
}
