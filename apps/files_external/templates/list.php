<?php /** @var $l \OCP\IL10N */ ?>
<div id="controls">
	<div id="file_action_panel"></div>
</div>
<div id='notification'></div>

<div id="emptycontent" class="hidden">
	<div class="icon-external"></div>
	<h2><?php p($l->t('No external storage configured or you don\'t have the permission to configure them')); ?></h2>
</div>

<input type="hidden" name="dir" value="" id="dir">

<table id="filestable" class="list-container <?php p($_['showgridview'] ? 'view-grid' : '') ?>">
	<thead>
		<tr>
			<th id='headerName' class="hidden column-name">
				<div id="headerName-container">
					<a class="name sort columntitle" data-sort="name"><span><?php p($l->t('Name')); ?></span><span class="sort-indicator"></span></a>
				</div>
			</th>
			<th id="headerBackend" class="hidden column-backend">
				<a class="backend sort columntitle" data-sort="backend"><span><?php p($l->t('Storage type')); ?></span><span class="sort-indicator"></span></a>
			</th>
			<th id="headerScope" class="hidden column-scope column-last">
				<a class="scope sort columntitle" data-sort="scope"><span><?php p($l->t('Scope')); ?></span><span class="sort-indicator"></span></a>
			</th>
		</tr>
	</thead>
	<tbody id="fileList">
	</tbody>
	<tfoot>
	</tfoot>
</table>
