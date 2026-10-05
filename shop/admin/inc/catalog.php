<?php
function saveStoreProduct(PDO $pdo,array $data,array $files,?int $id=null): int {
    $name=trim(strip_tags((string)($data['p_name'] ?? '')));
    $price=filter_var($data['p_current_price'] ?? '',FILTER_VALIDATE_FLOAT);
    $old=filter_var(($data['p_old_price'] ?? '')==='' ? 0 : $data['p_old_price'],FILTER_VALIDATE_FLOAT);
    $stock=filter_var($data['p_qty'] ?? '',FILTER_VALIDATE_INT);
    if(!$name || strlen($name)>255 || $price===false || !is_finite($price) || $price<0 || $price>99999999 || $old===false || $old<0 || $old>99999999 || $stock===false || $stock<0 || $stock>2147483647) throw new RuntimeException('Check the product name, price and whole-number stock quantity.');
    $category=(int)($data['ecat_id'] ?? 0);
    if($category <= 0) throw new RuntimeException('Choose a valid product category.');
    $stmt=$pdo->prepare('SELECT ecat_id FROM tbl_end_category WHERE ecat_id=?');
    $stmt->execute([$category]);
    if(!$stmt->fetchColumn()) throw new RuntimeException('Choose a valid product category.');
    $uploaded=[];
    $saveImage=static function(array $file,string $folder='') use (&$uploaded): ?string {
        if(($file['error'] ?? UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE) return null;
        $info=is_uploaded_file($file['tmp_name'] ?? '') ? @getimagesize($file['tmp_name']) : false;
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
        if(!$info || !isset($extensions[$info['mime']]) || ($file['error'] ?? 1)!==UPLOAD_ERR_OK || ($file['size'] ?? 0)>8388608) throw new RuntimeException('Choose a valid product image up to 8 MB.');
        $directory=dirname(__DIR__,2) . '/assets/uploads/' . $folder;
        if(!is_dir($directory) && !mkdir($directory,0775,true)) throw new RuntimeException('The image folder could not be created.');
        $filename='product-' . bin2hex(random_bytes(16)) . '.' . $extensions[$info['mime']];
        if(!move_uploaded_file($file['tmp_name'],$directory . $filename)) throw new RuntimeException('The image could not be saved.');
        $uploaded[]=$directory . $filename;
        return $filename;
    };
    try {
        $pdo->beginTransaction();
        $existing=null;
        if($id) {$stmt=$pdo->prepare('SELECT * FROM tbl_product WHERE p_id=? FOR UPDATE');$stmt->execute([$id]);$existing=$stmt->fetch();if(!$existing) throw new RuntimeException('Product not found.');}
        $photo=$saveImage($files['p_featured_photo'] ?? []) ?? ($existing['p_featured_photo'] ?? 'placeholder.svg');
        $values=[$name,round($old,2),round($price,2),$stock,$photo];
        foreach(['p_description','p_short_description','p_feature','p_condition','p_return_policy','p_video_link'] as $field) $values[]=substr((string)($data[$field] ?? ''),0,40000);
        $values[]=(int)(($data['p_is_featured'] ?? 0)==1);$values[]=(int)(($data['p_is_active'] ?? 1)==1);$values[]=$category;
        if($id) {
            $values[]=$id;
            $pdo->prepare('UPDATE tbl_product SET p_name=?,p_old_price=?,p_current_price=?,p_qty=?,p_featured_photo=?,p_description=?,p_short_description=?,p_feature=?,p_condition=?,p_return_policy=?,p_video_link=?,p_is_featured=?,p_is_active=?,ecat_id=? WHERE p_id=?')->execute($values);
        } else {
            $sql='INSERT INTO tbl_product(p_name,p_old_price,p_current_price,p_qty,p_featured_photo,p_description,p_short_description,p_feature,p_condition,p_return_policy,p_video_link,p_is_featured,p_is_active,ecat_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)';
            $stmt=$pdo->prepare($sql . (DB_DRIVER_NAME==='pgsql' ? ' RETURNING p_id' : ''));$stmt->execute($values);
            $id=(int)(DB_DRIVER_NAME==='pgsql' ? $stmt->fetchColumn() : $pdo->lastInsertId());
        }
        foreach(['size'=>['tbl_size','size_id','tbl_product_size'],'color'=>['tbl_color','color_id','tbl_product_color']] as $field=>[$table,$column,$join]) {
            $pdo->prepare("DELETE FROM $join WHERE p_id=?")->execute([$id]);
            foreach(array_unique((array)($data[$field] ?? [])) as $value) {
                if(!ctype_digit((string)$value)) throw new RuntimeException('Choose a valid product option.');
                $stmt=$pdo->prepare("SELECT $column FROM $table WHERE $column=?");$stmt->execute([$value]);
                if(!$stmt->fetchColumn()) throw new RuntimeException('A selected product option no longer exists.');
                $pdo->prepare("INSERT INTO $join($column,p_id) VALUES(?,?)")->execute([$value,$id]);
            }
        }
        $photos=$files['photo'] ?? [];
        if(count((array)($photos['tmp_name'] ?? []))>10) throw new RuntimeException('Upload up to 10 additional images at a time.');
        foreach((array)($photos['tmp_name'] ?? []) as $key=>$tmp) {
            $saved=$saveImage(['tmp_name'=>$tmp,'error'=>$photos['error'][$key],'size'=>$photos['size'][$key]],'product_photos/');
            if($saved) $pdo->prepare('INSERT INTO tbl_product_photo(photo,p_id) VALUES(?,?)')->execute([$saved,$id]);
        }
        $pdo->commit();return $id;
    } catch(Throwable $error) {
        if($pdo->inTransaction()) $pdo->rollBack();
        foreach($uploaded as $file) if(is_file($file)) unlink($file);
        throw $error;
    }
}
