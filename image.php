<?php
set_time_limit(0);
$dir="/mnt/sda1/photos";
$dest="/mnt/sda1/kod/data/temp/thumb/";

list_file($dir,$dest);

function list_file($dir,$dest){
    $list = scandir($dir); // 得到该文件下的所有文件和文件夹
    foreach($list as $file){//遍历
        $file_location=$dir."/".$file;//生成路径
        echo "file:$file_location\n";
        if(is_dir($file_location) && $file!="." &&$file!=".."){ //判断是不是文件夹
            echo "~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~folder~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
            list_file($file_location,$dest); //继续遍历
        }else{
            if($file!="." &&$file!=".."&& isImage($file_location)){ 
                thumb($file_location,$dest);
            }
        }
    }
}
function isImage($filename)
{
    $types = '.gif|.jpeg|.png|.bmp'; //定义检查的图片类型
    if(file_exists($filename))
    {
        $info = @getimagesize($filename);
        if ($info){
            $ext = image_type_to_extension($info['2']);
            return stripos($types,$ext);
        }else{
            return false;
        }
    }
    else
    {
        return false;
    }
}
function thumb($file_location,$dest){
    if (filesize($file_location) <= 1024*50 ||
        !function_exists('imagecolorallocate') ) {//小于50k或者不支持gd库 不再生成缩略图
            echo "jumpout: file too small or gd not support\n";
            return;
    }else{
        $image = $file_location;
        $imageMd5  = @md5_file($image);//文件md5
        
        if (strlen($imageMd5)<5) {
            $imageMd5 = md5($image);
        }
        $imageThumb = $dest.$imageMd5.'.png';
        if (!file_exists($imageThumb)){//如果拼装成的url不存在则没有生成过
 
                $cm = new ImageThumb($image,'file');
                $cm->prorate($imageThumb,250,250);//生成等比例缩略图
                echo "success!\n";
     
        }else{
            echo "jumpout: file exist\n";
        }
    }
}

class ImageThumb {
    var $srcFile = '';	//原图
    var $imgData = '';	//图片信息
    var $echoType;		//输出图片类型，link--不保存为文件；file--保存为文件
    var $im = '';		//临时变量
    var $srcW = '';		//原图宽
    var $srcH = '';		//原图高
    
    function __construct($srcFile, $echoType){
        $this->srcFile = $srcFile;
        $this->echoType = $echoType;
        $this->im = self::image($srcFile);
        if(!$this->im){
            return false;
        }
        
        $info = '';
        $this->imgData = GetImageSize($srcFile, $info);
        $this->srcW = imageSX($this->im);
        $this->srcH = imageSY($this->im);
        return $this;
    }
    public static function image($file){
        $info = '';
        $data = GetImageSize($file, $info);
        $img  = false;
        //var_dump($data,$file,memory_get_usage()-$GLOBALS['config']['appMemoryStart']);
        switch ($data[2]) {
            case IMAGETYPE_GIF:
                if (!function_exists('imagecreatefromgif')) {
                    break;
                }
                $img = imagecreatefromgif($file);
                break;
            case IMAGETYPE_JPEG:
                if (!function_exists('imagecreatefromjpeg')) {
                    break;
                }
                $img = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                if (!function_exists('imagecreatefrompng')) {
                    break;
                }
                $img = @imagecreatefrompng($file);
                imagesavealpha($img,true);
                break;
            case IMAGETYPE_XBM:
                $img = imagecreatefromxbm($file);
                break;
            case IMAGETYPE_WBMP:
                $img = imagecreatefromwbmp($file);
                break;
            case IMAGETYPE_BMP:
                $img = imagecreatefrombmp($file);
                break;
            default:break;
        }
        return $img;
    }
    
    public static function imageSize($file){
        $size = GetImageSize($file);
        if(!$size){
            return false;
        }
        return array('width'=>$size[0],"height"=>$size[1]);
    }
    
    // 生成扭曲型缩图
    function distortion($toFile, $toW, $toH){
        $cImg = $this->creatImage($this->im, $toW, $toH, 0, 0, 0, 0, $this->srcW, $this->srcH);
        return $this->echoImage($cImg, $toFile);
    }
    // 生成按比例缩放的缩图
    function prorate($toFile, $toW, $toH){
        $toWH = $toW / $toH;
        $srcWH = $this->srcW / $this->srcH;
        if ($toWH<=$srcWH) {
            $ftoW = $toW;
            $ftoH = $ftoW * ($this->srcH / $this->srcW);
        } else {
            $ftoH = $toH;
            $ftoW = $ftoH * ($this->srcW / $this->srcH);
        }
        if ($this->srcW > $toW || $this->srcH > $toH) {
            $cImg = $this->creatImage($this->im, $ftoW, $ftoH, 0, 0, 0, 0, $this->srcW, $this->srcH);
            return $this->echoImage($cImg, $toFile);
        } else {
            $cImg = $this->creatImage($this->im, $this->srcW, $this->srcH, 0, 0, 0, 0, $this->srcW, $this->srcH);
            return $this->echoImage($cImg, $toFile);
        }
    }
    // 生成最小裁剪后的缩图
    function cut($toFile, $toW, $toH){
        $toWH = $toW / $toH;
        $srcWH = $this->srcW / $this->srcH;
        if ($toWH<=$srcWH) {
            $ctoH = $toH;
            $ctoW = $ctoH * ($this->srcW / $this->srcH);
        } else {
            $ctoW = $toW;
            $ctoH = $ctoW * ($this->srcH / $this->srcW);
        }
        $allImg = $this->creatImage($this->im, $ctoW, $ctoH, 0, 0, 0, 0, $this->srcW, $this->srcH);
        $cImg = $this->creatImage($allImg, $toW, $toH, 0, 0, ($ctoW - $toW) / 2, ($ctoH - $toH) / 2, $toW, $toH);
        imageDestroy($allImg);
        return $this->echoImage($cImg, $toFile);
    }
    // 生成背景填充的缩图,默认用白色填充剩余空间，传入$isAlpha为真时用透明色填充
    function backFill($toFile, $toW, $toH,$isAlpha=false,$red=255, $green=255, $blue=255){
        $toWH = $toW / $toH;
        $srcWH = $this->srcW / $this->srcH;
        if ($toWH<=$srcWH) {
            $ftoW = $toW;
            $ftoH = $ftoW * ($this->srcH / $this->srcW);
        } else {
            $ftoH = $toH;
            $ftoW = $ftoH * ($this->srcW / $this->srcH);
        }
        if (function_exists('imagecreatetruecolor')) {
            @$cImg = imageCreateTrueColor($toW, $toH);
            if (!$cImg) {
                $cImg = imageCreate($toW, $toH);
            }
        } else {
            $cImg = imageCreate($toW, $toH);
        }
        
        $fromTop = ($toH - $ftoH)/2;//从正中间填充
        $backcolor = imagecolorallocate($cImg,$red,$green, $blue); //填充的背景颜色
        if ($isAlpha){//填充透明色
            $backcolor=imageColorTransparent($cImg,$backcolor);
            $fromTop = $toH - $ftoH;//从底部填充
        }
        
        imageFilledRectangle($cImg, 0, 0, $toW, $toH, $backcolor);
        if ($this->srcW > $toW || $this->srcH > $toH) {
            $proImg = $this->creatImage($this->im, $ftoW, $ftoH, 0, 0, 0, 0, $this->srcW, $this->srcH);
            if ($ftoW < $toW) {
                imageCopy($cImg, $proImg, ($toW - $ftoW) / 2, 0, 0, 0, $ftoW, $ftoH);
            } else if ($ftoH < $toH) {
                imageCopy($cImg, $proImg, 0, $fromTop, 0, 0, $ftoW, $ftoH);
            } else {
                imageCopy($cImg, $proImg, 0, 0, 0, 0, $ftoW, $ftoH);
            }
        } else {
            imageCopyMerge($cImg, $this->im, ($toW - $ftoW) / 2,$fromTop, 0, 0, $ftoW, $ftoH, 100);
        }
        return $this->echoImage($cImg, $toFile);
    }
    
    function creatImage($img, $creatW, $creatH, $dstX, $dstY, $srcX, $srcY, $srcImgW, $srcImgH){
        if (function_exists('imagecreatetruecolor')) {
            @$creatImg = ImageCreateTrueColor($creatW, $creatH);
            @imagealphablending($creatImg,false);//是不合并颜色,直接用$img图像颜色替换,包括透明色;
            @imagesavealpha($creatImg,true);//不要丢了$thumb图像的透明色;
            if ($creatImg){
                imageCopyResampled($creatImg, $img, $dstX, $dstY, $srcX, $srcY, $creatW, $creatH, $srcImgW, $srcImgH);
            }else {
                $creatImg = ImageCreate($creatW, $creatH);
                imageCopyResized($creatImg, $img, $dstX, $dstY, $srcX, $srcY, $creatW, $creatH, $srcImgW, $srcImgH);
            }
        } else {
            $creatImg = ImageCreate($creatW, $creatH);
            imageCopyResized($creatImg, $img, $dstX, $dstY, $srcX, $srcY, $creatW, $creatH, $srcImgW, $srcImgH);
        }
        return $creatImg;
    }
    
    
    // Rotate($toFile, 90);
    public function imgRotate($toFile,$degree) {
        if (!$this->im ||
            $degree % 360 === 0 ||
            !function_exists('imageRotate')) {
                return false;
            }
            $rotate  = imageRotate($this->im,360-$degree,0);
            $result  = false;
            switch ($this->imgData[2]) {
                case IMAGETYPE_GIF:
                    $result = imagegif($rotate, $toFile);
                    break;
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($rotate, $toFile,100);//压缩质量
                    break;
                case IMAGETYPE_PNG:
                    $result = imagePNG($rotate, $toFile);
                    break;
                default:break;
            }
            imageDestroy($rotate);
            imageDestroy($this->im);
            return $result;
    }
    
    // 输出图片，link---只输出，不保存文件。file--保存为文件
    function echoImage($img, $toFile){
        if(!$img) return false;
        ob_get_clean();
        $result = false;
        switch ($this->echoType) {
            case 'link':$result = imagePNG($img);break;
            case 'file':$result = imagePNG($img, $toFile);break;
            //return ImageJpeg($img, $to_File);
        }
        imageDestroy($img);
        imageDestroy($this->im);
        return $result;
    }
}
?>