<?php
/*
* @version 0.2 (auto-set)
*/

  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }

  $table_name='objects';
  $rec=SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");



  if ($this->mode=='update') {
   $ok=1;
  // step: default
  if ($this->tab=='') {
  //updating 'TITLE' (varchar, required)
   global $title;

   $rec['TITLE']=$title;

   $rec['TITLE']=str_replace(' ', '', trim($rec['TITLE']));

   $tmp=SQLSelectOne("SELECT ID FROM objects WHERE TITLE LIKE '".DBSafe($rec['TITLE'])."' AND ID!=".(int)$rec['ID']);
   if ($tmp['ID']) {
    $rec['TITLE']='';
   }

   if ($rec['TITLE']=='') {
    $out['ERR_TITLE']=1;
    $ok=0;
   }
  //updating 'Class' (select, required)
   global $class_id;
   if ($rec['CLASS_ID'] && $class_id!=$rec['CLASS_ID']) {
    $class_changed_from=$rec['CLASS_ID'];
   }
   $rec['CLASS_ID']=$class_id;
   if (!$rec['CLASS_ID']) {
    $out['ERR_CLASS_ID']=1;
    $ok=0;
   }
  //updating 'Description' (text)
   global $description;
   $rec['DESCRIPTION']=$description;
  //updating 'Location' (select)
   global $location_id;
   $rec['LOCATION_ID']=(int)$location_id;

   global $keep_history;
   $rec['KEEP_HISTORY']=(int)$keep_history;


  }
  // step: properties
  if ($this->tab=='properties') {
  }
  // step: methods
  if ($this->tab=='methods') {
  }
  // step: history
  if ($this->tab=='history') {
  }
  //UPDATING RECORD
   if ($ok) {
    if ($rec['ID']) {
     SQLUpdate($table_name, $rec); // update

     if ($class_changed_from) {
      // class changed from $class_changed_from to $rec['CLASS_ID']
      // step 1. take all properties out of class
      $pvalues=SQLSelect("SELECT pvalues.*, properties.TITLE as PROPERTY_TITLE FROM pvalues LEFT JOIN properties ON pvalues.PROPERTY_ID=properties.ID WHERE properties.CLASS_ID!=0 AND pvalues.OBJECT_ID='".$rec['ID']."'");
      $total=count($pvalues);
      for($i=0;$i<$total;$i++) {
       $new_property=array();
       $new_property['TITLE']=$pvalues[$i]['PROPERTY_TITLE'];
       $new_property['CLASS_ID']=0;
       $new_property['OBJECT_ID']=$rec['ID'];
       //$new_property['VALUE']='';
       $new_property['ID']=SQLInsert('properties', $new_property);
       $pvalues[$i]['PROPERTY_ID']=$new_property['ID'];
       unset($pvalues[$i]['PROPERTY_TITLE']);
       SQLUpdate('pvalues', $pvalues[$i]);
      }
      // step 2. apply matched properties of new class
      $properties=$this->getParentProperties($rec['CLASS_ID'], '', 1);
      $total=count($properties);
      for($i=0;$i<$total;$i++) {
       $pvalue=SQLSelectOne("SELECT pvalues.* FROM pvalues LEFT JOIN properties ON pvalues.PROPERTY_ID=properties.ID WHERE properties.CLASS_ID=0 AND pvalues.OBJECT_ID='".$rec['ID']."' AND properties.TITLE LIKE '".DBSafe($properties[$i]['TITLE'])."'");
       if ($pvalue['ID']) {
        $old_prop=$pvalue['PROPERTY_ID'];
        $pvalue['PROPERTY_ID']=$properties[$i]['ID'];
        SQLUpdate('pvalues', $pvalue);
        SQLExec("DELETE FROM properties WHERE ID='".$old_prop."'");
       }
      }
     }

    } else {
     $new_rec=1;
     $rec['ID']=SQLInsert($table_name, $rec); // adding new record
    }
    $out['OK']=1;
   } else {
    $out['ERR']=1;
   }
  }
  // step: default
  if ($this->tab=='') {
  //options for 'Class' (select)
  $tmp=SQLSelect("SELECT ID, TITLE FROM classes ORDER BY TITLE");
  $classes_total=count($tmp);
  for($classes_i=0;$classes_i<$classes_total;$classes_i++) {
   $class_id_opt[$tmp[$classes_i]['ID']]=$tmp[$classes_i]['TITLE'];
  }
  for($i=0;$i<$classes_total;$i++) {
   if ($rec['CLASS_ID']==$tmp[$i]['ID']) $tmp[$i]['SELECTED']=1;
  }
  $out['CLASS_ID_OPTIONS']=$tmp;
  //options for 'Location' (select)
  $tmp=SQLSelect("SELECT ID, TITLE FROM locations ORDER BY TITLE");
  $locations_total=count($tmp);
  for($locations_i=0;$locations_i<$locations_total;$locations_i++) {
   $location_id_opt[$tmp[$locations_i]['ID']]=$tmp[$locations_i]['TITLE'];
  }
  for($i=0;$i<$locations_total;$i++) {
   if ($rec['LOCATION_ID']==$tmp[$i]['ID']) $tmp[$i]['SELECTED']=1;
  }
  $out['LOCATION_ID_OPTIONS']=$tmp;
  }
  // step: properties
  if ($this->tab=='properties') {

   global $delete_prop;
   if ($delete_prop) {
    $pr=SQLSelectOne("SELECT * FROM properties WHERE ID='".$delete_prop."'");
    if ($pr['ID']) {
     $value=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".$delete_prop."' AND OBJECT_ID='".$rec['ID']."'");
     if ($value['ID']) {
      SQLExec("DELETE FROM phistory WHERE VALUE_ID='".$value['ID']."'");
      SQLExec("DELETE FROM pvalues WHERE PROPERTY_ID='".$delete_prop."' AND OBJECT_ID='".$rec['ID']."'");
     }
     if (!$pr['CLASS_ID']) {
      SQLExec("DELETE FROM properties WHERE ID='".$delete_prop."' AND OBJECT_ID='".$rec['ID']."'");
     }
    }
   }

   if ($this->mode=='update') {
    global $new_property;
    global $new_value;
    
    if ($new_property!='') {
     $tmp=array();
     $tmp['TITLE']=$new_property;
     $tmp['OBJECT_ID']=$rec['ID'];
     $tmp['ID']=SQLInsert('properties', $tmp);
     if ($new_value!='') {
      $tmp2=array();
      $tmp2['PROPERTY_ID']=$tmp['ID'];
      $tmp2['OBJECT_ID']=$rec['ID'];
      $tmp2['VALUE']=$new_value.'';
      SQLInsert('pvalues', $tmp2);
     }
    }
   }


   include_once(DIR_MODULES.'classes/classes.class.php');
   $cl=new classes();
   $props=$cl->getParentProperties($rec['CLASS_ID'], '', 1);

   $my_props=SQLSelect("SELECT * FROM properties WHERE OBJECT_ID='".$rec['ID']."'");
   if ($my_props[0]['ID']) {
    foreach($my_props as $p) {
     $props[]=$p;
    }
   }

   $total=count($props);
   //print_R($props);exit;
   for($i=0;$i<$total;$i++) {
    if (!$props[$i]['KEEP_HISTORY'] && $rec['KEEP_HISTORY']>0) {
     $props[$i]['KEEP_HISTORY']=$rec['KEEP_HISTORY'];
    }
    $value=SQLSelectOne("SELECT * FROM pvalues WHERE PROPERTY_ID='".$props[$i]['ID']."' AND OBJECT_ID='".$rec['ID']."'");
    if ($this->mode=='update') {
     global ${"value".$props[$i]['ID']};
        if (isset(${"value".$props[$i]['ID']}) && (${"value".$props[$i]['ID']}!=$this->getProperty($props[$i]['TITLE']))) {
            $this->class_id=$rec['CLASS_ID'];
            $this->id=$rec['ID'];
            $this->object_title=$rec['TITLE'];
            $this->setProperty($props[$i]['TITLE'], ${"value".$props[$i]['ID']});
            //echo "Updating ".$props[$i]['TITLE']."<br/>";
        }
     /*
     SQLExec("DELETE FROM pvalues WHERE PROPERTY_ID='".$props[$i]['ID']."' AND OBJECT_ID='".$rec['ID']."'");
     $tmp=array();
     $tmp['PROPERTY_ID']=$props[$i]['ID'];
     $tmp['OBJECT_ID']=$rec['ID'];
     $tmp['VALUE']=${"value".$props[$i]['ID']};
     $tmp['ID']=SQLInsert('pvalues', $tmp);
     */
     $value['VALUE']=${"value".$props[$i]['ID']};
    }
    $props[$i]['VALUE']=$value['VALUE'];
    $props[$i]['LINKED_MODULES']=$value['LINKED_MODULES'];
   }

   $out['PROPERTIES']=$props;

  }
  // step: methods
  if ($this->tab=='methods') {


   global $overwrite;
   global $delete_meth;

   if ($delete_meth) {
    $method=SQLSelectOne("SELECT * FROM methods WHERE ID='".(int)$delete_meth."'");
    $my_meth=SQLSelectOne("SELECT * FROM methods WHERE OBJECT_ID='".$rec['ID']."' AND TITLE LIKE '".DBSafe($method['TITLE'])."'");
    SQLExec("DELETE FROM methods WHERE OBJECT_ID='".$rec['ID']."' AND TITLE LIKE '".DBSafe($method['TITLE'])."'");
   }

   if ($overwrite) {
    global $method_id;
    $method=SQLSelectOne("SELECT * FROM methods WHERE ID='".(int)$method_id."'");

    if ($method['OBJECT_ID']) {
     $obj=SQLSelectOne("SELECT ID, CLASS_ID FROM objects WHERE ID='".$method['OBJECT_ID']."'");
     $method=SQLSelectOne("SELECT * FROM methods WHERE TITLE LIKE '".$method['TITLE']."' AND CLASS_ID='".$obj['CLASS_ID']."'");
    }

    $out['METHOD_CLASS_ID']=$method['CLASS_ID'];
    $tmp=SQLSelectOne("SELECT * FROM classes WHERE ID='".$method['CLASS_ID']."'");
    $out['METHOD_CLASS_TITLE']=$tmp['TITLE'];
    $out['METHOD_TITLE']=$method['TITLE'];
    $out['METHOD_TITLE_URL']=urlencode($method['TITLE']);
    $out['OBJECT_TITLE']=$rec['TITLE'];
    $out['OBJECT_TITLE_URL']=urlencode($rec['TITLE']);
    $out['METHOD_ID']=$method['ID'];
    $my_meth=SQLSelectOne("SELECT * FROM methods WHERE OBJECT_ID='".$rec['ID']."' AND TITLE LIKE '".DBSafe($method['TITLE'])."'");

    if ($this->mode=='update') {
       $ok=1;
       global $code;
       global $call_parent;
       global $run_type;


       $my_meth['CODE']=$code;
       $my_meth['CALL_PARENT']=$call_parent;
       $my_meth['TITLE']=$method['TITLE'];
       $my_meth['OBJECT_ID']=$rec['ID'];

       if ($run_type=='script') {
        global $script_id;
        $my_meth['SCRIPT_ID']=$script_id;
       } else {
        $my_meth['SCRIPT_ID']=0;
       }

   if ($run_type=='code' && $my_meth['CODE']!='') {
    //echo $content;
    $errors=php_syntax_error($my_meth['CODE']);
    if ($errors) {
     $out['ERR_CODE']=1;
     $out['ERRORS']=nl2br($errors);
     $ok=0;
    }
    $out['CODE']=$my_meth['CODE'];
   }

    if ($ok) {

       if ($my_meth['ID']) {
        SQLUpdate('methods', $my_meth);
       } else {
        $my_meth['ID']=SQLInsert('methods', $my_meth);
       }
       $out['OK']=1;
       
    }

    }
    if (!$my_meth['ID']) {
     $out['CALL_PARENT']=1;
    } else {
     $out['CODE']=htmlspecialchars($my_meth['CODE']);
     $out['SCRIPT_ID']=($my_meth['SCRIPT_ID']);
     $out['CALL_PARENT']=(int)($my_meth['CALL_PARENT']);
    }
    $out['OVERWRITE']=1;
   }

   include_once(DIR_MODULES.'classes/classes.class.php');
   $cl=new classes();
   $methods=$cl->getParentMethods($rec['CLASS_ID'], '', 1);
   $total=count($methods);
   for($i=0;$i<$total;$i++) {
    $my_meth=SQLSelectOne("SELECT ID FROM methods WHERE OBJECT_ID='".$rec['ID']."' AND TITLE LIKE '".DBSafe($methods[$i]['TITLE'])."'");
    if ($my_meth['ID']) {
     $methods[$i]['CUSTOMIZED']=1;
    }
   }
   $out['METHODS']=$methods;

  }
  // step: history
  if ($this->tab=='history') {
  }
  if (is_array($rec)) {
   foreach($rec as $k=>$v) {
    if (!is_array($v)) {
     $rec[$k]=htmlspecialchars($v);
    }
   }
  }
  outHash($rec, $out);

  if (!$rec['ID'] && $this->class_id) {
   $out['CLASS_ID']=$this->class_id;
  }

  $out['SCRIPTS']=SQLSelect("SELECT ID, TITLE FROM scripts ORDER BY TITLE");


?>