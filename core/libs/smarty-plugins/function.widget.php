<?php

function smarty_function_widget($params, $template){
	
	$name = $params['name'];
	
	$model = new WidgetModel($name);
	$model->execute();
	var_dump($name, $model, WidgetModel::SplitBaseURL($name));
	return '';
	
	// I don't really care what it's called!
	if(isset($params['file'])) $file = $params['file'];
	elseif(isset($params['src'])) $file = $params['src'];
	elseif(isset($params['href'])) $file = $params['href'];
	
	$f = Core::ResolveAsset($file);
	
	if(isset($params['assign'])) $template->assign($params['assign'], $f);
	else return $f;
}
