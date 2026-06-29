<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title></title>
</head>
<body>
	<form action="<?php echo base_url('imports/import1') ?>" method="post" enctype="multipart/form-data">
		<div><label for="">Archivo a importar</label></div>
		<div>
			<input type="file" name="file">
		</div>
		<div>
			<input type="submit" value="import">
		</div>
	</form>
</body>
</html>