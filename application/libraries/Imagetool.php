<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
	
	class Imagetool{

		private $CI;
		private $imgs_path;
		private $thumb_width;
		private $thumb_height;
		private $imgs_abs_path;

		public function __construct(){

			$this->imgs_path =  '';
			$this->imgs_abs_path =  str_replace("\\","/",FCPATH) . 'uploads/';
			$this->CI =& get_instance();
			$upload_config = array();

			$upload_config['allowed_types'] = '*';
			$upload_config['max_size']	= '40906';
			
			$this->CI->load->library('upload',$upload_config);
		}

		public function setUploadPath( $upload_path ){

			$this->thumb_width = 0;
			$this->thumb_height = 0;
			$this->imgs_path = $this->imgs_abs_path . $upload_path . '/';

			if( !file_exists( $this->imgs_abs_path . $upload_path ) ){

				if( !mkdir( $this->imgs_abs_path . $upload_path ) ){

					throw new Exception('No se pudo crear el directorio');
					
				}
			}

			$this->CI->upload->set_upload_path( $this->imgs_path );
		}

		public function uploadImg($image_name, $multiple_upload = NULL ){

			try{

				if( ! $this->CI->upload->do_upload($image_name, $multiple_upload) ){

					$error = $this->CI->upload->display_errors();
					throw new Exception('Ocurri&oacute; un error subiendo la imagen al servidor.'.$error);
				}
				
				return $this->CI->upload->data();

			}
			catch(Exception $e){

				throw new Exception( $e->getMessage() );
			}

		}

		public function resize($src_img_name, $max_width, $max_height, $keep_ratio = TRUE ){

			$full_img_path = $src_img_name;
			$new_img = null;
			$new_width = 0;
			$new_height = 0;
			$offest_x =  0;
			$offest_y = 0;

			try{

				if( is_readable($full_img_path) ){

					list($src_img_width, $src_img_height, $src_img_type, $src_img_attr) = getimagesize($full_img_path);

					switch( $src_img_type ){

						case IMAGETYPE_JPEG:{

							$new_img = imagecreatefromjpeg($full_img_path);
						}
						break;
						case IMAGETYPE_PNG:{

							$new_img = imagecreatefrompng($full_img_path);
						}
						break;
						case IMAGETYPE_GIF:{

							$new_img = imagecreatefromgif($full_img_path);
						}
						break;
						default:{

							throw new Exception('Tipo de imagen no soportada');
						}
					}

					if( $keep_ratio == TRUE ){

						$new_width = $max_width;
						$new_height = ($src_img_height / $src_img_width) * $new_width;

						if( ($src_img_width > $max_width) ){

							if( $new_height > $max_height ){

								$new_width = ( $max_height * $max_width ) / $new_height;
								$new_height = $max_height;
							}
							
						}
						else if( $src_img_height > $max_height ){

							$new_width = ( $max_height * $max_width ) / $new_height;
							$new_height = $max_height;
						}
						else{

							$new_width = $src_img_width;
							$new_height = $src_img_height;
						}

						$new_height = ceil($new_height);
						$new_width = ceil($new_width);
						
						if( $new_width < $max_width ){

							$offest_x = ( $max_width - $new_width ) / 2;
						}

						if( $new_height < $max_height ){

							$offest_y = ( $max_height - $new_height ) / 2;
						}

					}
					else{

						$new_width = $max_width;
						$new_height = $max_height;
					}

					
					$canvas = imagecreatetruecolor($max_width, $max_height);
					$red = $green = $blue = 255;

					$bg = imagecolorallocate($canvas, $red, $green, $blue);
	                imagefill($canvas, 0, 0, $bg);
					imagecopyresampled($canvas, $new_img, $offest_x, $offest_y, 0, 0, $new_width, $new_height, $src_img_width, $src_img_height);

					$dest_img_name = 'thumb_' . substr( $full_img_path, strripos($full_img_path, '/') + 1 );

					switch( $src_img_type ){

						case IMAGETYPE_JPEG:{

							imagejpeg($canvas, $this->imgs_path . $dest_img_name, 100);
						}
						break;
						case IMAGETYPE_PNG:{

							imagepng($canvas, $this->imgs_path . $dest_img_name, 0);
						}
						break;
						case IMAGETYPE_GIF:{

							imagegif($canvas, $this->imgs_path . $dest_img_name );
						}
						break;
					}

				}
				else{

					throw new Exception('Imagen no encontrada');
				}

				return $dest_img_name;

			}
			catch(Exception $e){

				throw new Exception( $e->getMessage() );
			}

		}

		private function getRescaleMode( $img_info ){

			if( $img_info['width'] < $this->thumb_width && $img_info['height'] < $this->thumb_height ){

				return 'CROP_WH';
			}

			if( $img_info['width'] < $this->thumb_width && $img_info['height'] > $this->thumb_height ){

				return 'CROP_HEIGHT';
			}

			if( $img_info['width'] > $this->thumb_width && $img_info['height'] < $this->thumb_height ){

				return 'CROP_WIDTH';
			}

			return 'CROP_WH';
		}

		private function getCropArea( $mode, $img_info ){

			$crop_area = array( 

							'width' => 0,
							'height' => 0,
							'x_axis' => 0,
							'y_axis' => 0
						);

			switch( $mode ){

				case 'CROP_HEIGHT':{

					$crop_area['width'] = $img_info['width'];
					$crop_area['height'] = $this->thumb_height;
					$crop_area['x_axis'] = 0;
					$crop_area['y_axis'] = ( $img_info['height'] - $this->thumb_height ) / 2;
				}
				break;
				case 'CROP_WIDTH':{

					$crop_area['width'] = $this->thumb_width;
					$crop_area['height'] = $img_info['height'];
					$crop_area['x_axis'] = ( $img_info['width'] - $this->thumb_width ) / 2;
					$crop_area['y_axis'] = 0;
				}
				break;
				case 'CROP_WH':{

					$ratio = $img_info['width'] / $img_info['height'];
					$resized_height = floor( $this->thumb_width / $ratio );

					$crop_area['width'] = $this->thumb_width;
					$crop_area['x_axis'] = 0;			

					if( $resized_height < $this->thumb_height ){

						while( $resized_height < $this->thumb_height ){

							$crop_area['width']++;
							$resized_height = floor( $crop_area['width'] / $ratio );						
						}

						$crop_area['x_axis'] = ( $crop_area['width'] - $this->thumb_width ) / 2 ;
					}			
					
					$crop_area['height'] = $resized_height;				
					$crop_area['y_axis'] = ( $resized_height - $this->thumb_height ) / 2;
				}
				break;
			}

			return $crop_area;
		}

		public function rescale( $image_path, $width, $height, $no_thumb = FALSE ){

			$this->thumb_width = $width;
			$this->thumb_height = $height;

			list($src_img_width, $src_img_height, $src_img_type) = getimagesize($image_path);
			$img_info['width'] = $src_img_width;
			$img_info['height'] = $src_img_height;
			$img_info['type'] = $src_img_type;

			$mode = $this->getRescaleMode( $img_info );

			if( $mode == NULL ){

				return;
			}

			$crop_area = $this->getCropArea( $mode, $img_info );

			$src_image = NULL;

			switch( $img_info['type'] ){

				case IMAGETYPE_JPEG:{

					$src_image = imagecreatefromjpeg($image_path);
				}
				break;
				case IMAGETYPE_PNG:{

					$src_image = imagecreatefrompng($image_path);
				}
				break;
				case IMAGETYPE_GIF:{

					$src_image = imagecreatefromgif($image_path);
				}
				break;
				default:{

					throw new Exception('Tipo de imagen no soportada');
				}
			}

			$canvas = imagecreatetruecolor( $crop_area['width'], $crop_area['height'] );

			if( $mode == 'CROP_WH' ){

				imagecopyresampled(
					$canvas,
					$src_image,
					0,0,0,0,
					$crop_area['width'], $crop_area['height'],
					$img_info['width'], $img_info['height']
				);

				$canvas_crop = imagecreatetruecolor( $this->thumb_width, $this->thumb_height );

				imagecopyresampled(
					$canvas_crop,
					$canvas,
					0,0,
					$crop_area['x_axis'], $crop_area['y_axis'],
					$this->thumb_width, $this->thumb_height,
					$this->thumb_width, $this->thumb_height
				);

				unset($canvas);
				$canvas = $canvas_crop;
			}
			else{

				imagecopyresampled(
					$canvas,
					$src_image,
					0,0,
					$crop_area['x_axis'], $crop_area['y_axis'],
					$crop_area['width'], $crop_area['height'],
					$crop_area['width'], $crop_area['height']
				);

			}

			if( $no_thumb ){

				$dest_img_name = substr( $image_path, strripos($image_path, '/') + 1 );
			}
			else{

				$dest_img_name = 'thumb_x_' . substr( $image_path, strripos($image_path, '/') + 1 );
			}

			switch( $img_info['type'] ){

				case IMAGETYPE_JPEG:{

					imagejpeg($canvas, $this->imgs_path . $dest_img_name, 100);
				}
				break;
				case IMAGETYPE_PNG:{

					imagepng($canvas, $this->imgs_path. $dest_img_name, 0);
				}
				break;
				case IMAGETYPE_GIF:{

					imagegif($canvas, $this->imgs_path . $dest_img_name );
				}
				break;
			}

			unset($canvas);

		}

	}
	
?>