<?= $this->extend('competition') ?>

<?= $this->section('subcontent') ?>
	<?php if (!$isFinished) : ?>
		<div class="bp3-callout bp3-intent-warning">
			<p>Ini adalah hasil sementara, yang hanya menampilkan <b>hasil 4 jam pertama</b> setiap harinya.</p>
			<hr />
			<p>These are preliminary results, showing only the <b>results from the first 4 hours</b> of each day.</p>
		</div>
		<br />
	<?php endif; ?>
	<?= $table ?>
<?= $this->endSection() ?>
