<?php

declare (strict_types=1);
/**
 * Fuel is a fast, lightweight, community driven PHP 5.4+ framework.
 *
 * @package    Fuel
 * @version    1.8.2
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2019 Fuel Development Team
 * @link       https://fuelphp.com
 */
namespace Fuel\Core;

class Image_Imagick extends \Image_Driver
{
    protected $accepted_extensions = ['png', 'gif', 'jpg', 'jpeg'];
    protected $imagick;
    public function load($filename, $return_data = false, $force_extension = false)
    {
        extract(parent::load($filename, $return_data, $force_extension));
        if ($this->imagick == null) {
            $this->imagick = new \Imagick();
        }
        $this->imagick->read_image($filename);
        // deal with exif autorotation
        $orientation = $this->imagick->get_image_orientation();
        match ($orientation) {
            \Imagick::ORIENTATION_BOTTOMRIGHT => $this->imagick->rotateimage('#000', 180),
            \Imagick::ORIENTATION_RIGHTTOP => $this->imagick->rotateimage('#000', 90),
            \Imagick::ORIENTATION_LEFTBOTTOM => $this->imagick->rotateimage('#000', -90),
            default => $this,
        };
        return $this;
    }
    protected function _crop($x1, $y1, $x2, $y2)
    {
        extract(parent::_crop($x1, $y1, $x2, $y2));
        $width = $x2 - $x1;
        $height = $y2 - $y1;
        $this->debug('Cropping image ' . $width . 'x' . $height . "+{$x1}+{$y1} based on coords ({$x1}, {$y1}), ({$x2}, {$y2})");
        $this->imagick->crop_image($width, $height, $x1, $y1);
        $this->imagick->set_image_page(0, 0, 0, 0);
    }
    protected function _resize($width, $height = null, $keepar = true, $pad = true)
    {
        extract(parent::_resize($width, $height, $keepar, $pad));
        $this->imagick->scale_image($width, $height, $keepar);
        if ($pad) {
            $tmpimage = new \Imagick();
            $tmpimage->new_image($cwidth, $cheight, $this->create_color('#000', 0), 'png');
            $tmpimage->composite_image($this->imagick, \Imagick::COMPOSITE_DEFAULT, ($cwidth - $width) / 2, ($cheight - $height) / 2);
            $this->imagick = $tmpimage;
        }
    }
    protected function _rotate($degrees)
    {
        extract(parent::_rotate($degrees));
        $this->imagick->rotate_image($this->create_color('#000', 0), $degrees);
    }
    protected function _watermark($filename, $position, $padding = [5, 5])
    {
        extract(parent::_watermark($filename, $position, $padding));
        $wmimage = new \Imagick();
        $wmimage->read_image($filename);
        $wmimage->evaluate_image(\Imagick::EVALUATE_MULTIPLY, $this->config['watermark_alpha'] / 100, \Imagick::CHANNEL_ALPHA);
        $this->imagick->composite_image($wmimage, \Imagick::COMPOSITE_DEFAULT, $x, $y);
    }
    protected function _flip($direction)
    {
        switch ($direction) {
            case 'vertical':
                $this->imagick->flip_image();
                break;
            case 'horizontal':
                $this->imagick->flop_image();
                break;
            case 'both':
                $this->imagick->flip_image();
                $this->imagick->flop_image();
                break;
            default:
                return false;
        }
    }
    protected function _border($size, $color = null)
    {
        extract(parent::_border($size, $color));
        $this->imagick->border_image($this->create_color($color, 100), $size, $size);
    }
    protected function _mask($maskimage)
    {
        extract(parent::_mask($maskimage));
        $wmimage = new \Imagick();
        $wmimage->read_image($maskimage);
        $wmimage->set_image_matte(false);
        $this->imagick->composite_image($wmimage, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);
    }
    protected function _rounded($radius, $sides, $antialias = 0)
    {
        extract(parent::_rounded($radius, $sides, null));
        $sizes = $this->sizes();
        $sizes->width_half = $sizes->width / 2;
        $sizes->height_half = $sizes->height / 2;
        $list = [];
        if (!$tl) {
            $list = ['x' => 0, 'y' => 0];
        }
        if (!$tr) {
            $list = ['x' => $sizes->width_half, 'y' => 0];
        }
        if (!$bl) {
            $list = ['x' => 0, 'y' => $sizes->height_half];
        }
        if (!$br) {
            $list = ['x' => $sizes->width_half, 'y' => $sizes->height_half];
        }
        foreach ($list as $index => $element) {
            $image = $this->imagick->clone();
            $image->crop_image($sizes->width_half, $sizes->height_half, $element['x'], $element['y']);
            $list[$index]['image'] = $image;
        }
        $this->imagick->round_corners($radius, $radius);
        foreach ($list as $element) {
            $this->imagick->composite_image($element['image'], \Imagick::COMPOSITE_DEFAULT, $element['x'], $element['y']);
        }
    }
    protected function _grayscale()
    {
        $this->imagick->set_image_type(\Imagick::IMGTYPE_GRAYSCALEMATTE);
    }
    public function sizes($filename = null, $usecache = true)
    {
        if ($filename === null) {
            return (object) ['width' => $this->imagick->get_image_width(), 'height' => $this->imagick->get_image_height()];
        }
        $tmpimage = new \Imagick();
        $tmpimage->read_image($filename);
        return (object) ['width' => $tmpimage->get_image_width(), 'height' => $tmpimage->get_image_height()];
    }
    public function save($filename = null, $permissions = null)
    {
        extract(parent::save($filename, $permissions));
        $this->run_queue();
        $this->add_background();
        $filetype = $this->image_extension;
        if ($filetype == 'jpg' or $filetype == 'jpeg') {
            $filetype = 'jpeg';
        }
        if ($this->imagick->get_image_format() != $filetype) {
            $this->imagick->set_image_format($filetype);
        }
        if ($this->imagick->get_image_format() == 'jpeg' and $this->config['quality'] != 100) {
            $this->imagick->set_image_compression(\Imagick::COMPRESSION_JPEG);
            $this->imagick->set_image_compression_quality($this->config['quality']);
            $this->imagick->strip_image();
        }
        file_put_contents($filename, $this->imagick->get_image_blob());
        if ($this->config['persistence'] === false) {
            $this->reload();
        }
        return $this;
    }
    public function output($filetype = null)
    {
        extract(parent::output($filetype));
        $this->run_queue();
        $this->add_background();
        if ($filetype == 'jpg' or $filetype == 'jpeg') {
            $filetype = 'jpeg';
        }
        if ($this->imagick->get_image_format() != $filetype) {
            $this->imagick->set_image_format($filetype);
        }
        if ($this->imagick->get_image_format() == 'jpeg' and $this->config['quality'] != 100) {
            $this->imagick->set_image_compression(\Imagick::COMPRESSION_JPEG);
            $this->imagick->set_image_compression_quality($this->config['quality']);
            $this->imagick->strip_image();
        }
        if (!$this->config['debug']) {
            echo $this->imagick->get_image_blob();
        }
        return $this;
    }
    protected function add_background()
    {
        if ($this->config['bgcolor'] != null) {
            $tmpimage = new \Imagick();
            $sizes = $this->sizes();
            $tmpimage->new_image($sizes->width, $sizes->height, $this->create_color($this->config['bgcolor'], $this->config['bgcolor'] == null ? 0 : 100), 'png');
            $tmpimage->composite_image($this->imagick, \Imagick::COMPOSITE_DEFAULT, 0, 0);
            $this->imagick = $tmpimage;
        }
    }
    /**
     * Creates a new color usable by Imagick.
     *
     * @param  string   $hex    The hex code of the color
     * @param  integer  $newalpha  The alpha of the color, 0 (trans) to 100 (opaque)
     * @return string   rgba representation of the hex and alpha values.
     */
    protected function create_color($hex, $newalpha = null)
    {
        // Convert hex to rgba
        extract($this->create_hex_color($hex));
        // If a custom alpha was passed, use that
        isset($newalpha) and $alpha = $newalpha;
        return new \Imagick_Pixel('rgba(' . $red . ', ' . $green . ', ' . $blue . ', ' . round($alpha / 100, 2) . ')');
    }
}