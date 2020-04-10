<div class="section">
	<h2 data-anchor-name="ssl-root-certificate"><?php p($l->t('SSL Root Certificates')); ?></h2>
	<table id="sslCertificate" class="grid" data-type="<?php p($_['type']); ?>">
		<thead>
			<tr>
				<th><?php p($l->t('Common Name')); ?></th>
				<th><?php p($l->t('Valid until')); ?></th>
				<th><?php p($l->t('Issued By')); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($_['certs'] as $rootCert): /**@var \OCP\ICertificate $rootCert */ ?>
			<tr class="<?php echo $rootCert->isExpired() ? 'expired' : 'valid' ?>"
				data-name="<?php p($rootCert->getName()) ?>">
				<td class="rootCert"
					title="<?php p($rootCert->getOrganization()) ?>">
					<?php p($rootCert->getCommonName()) ?>
				</td>
				<td title="<?php p($l->t('Valid until %s', $l->l('date', $rootCert->getExpireDate()))) ?>">
					<?php echo $l->l('date', $rootCert->getExpireDate()) ?>
				</td>
				<td title="<?php p($rootCert->getIssuerOrganization()) ?>">
					<?php p($rootCert->getIssuerName()) ?>
				</td>
				<td <?php if ($rootCert != ''): ?>class="remove"
					<?php else: ?>style="visibility:hidden;"
					<?php endif; ?>><img alt="<?php p($l->t('Delete')); ?>"
										 title="<?php p($l->t('Delete')); ?>"
										 class="action"
										 src="<?php print_unescaped(image_path('core', 'actions/delete.svg')); ?>"/>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<form class="uploadButton" method="post"
		  action="<?php p($_['urlGenerator']->linkToRoute($_['uploadRoute'])); ?>"
		  target="certUploadFrame">
		<label for="rootcert_import" class="inlineblock button"
			   id="rootcert_import_button"><?php p($l->t('Import root certificate')); ?></label>
		<input type="file" id="rootcert_import" name="rootcert_import"
			   class="hiddenuploadfield">
	</form>
</div>
