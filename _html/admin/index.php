<?php

	include dirname(__FILE__) .'/../kernel/kernel.php';
	$CMS = new Kernel();
	if ($CMS->callModule('index', null, null, 'admin'))
	{
		$output = $CMS->compile();
		// мы посылаем хедер только после компиляции на случай, если произойдёт ошибка,
		// обработка которой потребует иного хедера нежели тот, что был заготовлен.
		header('Content-type: ' . $CMS->outputHeader, true);
		echo $output;
	}

?>