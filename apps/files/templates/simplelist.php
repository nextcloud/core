<div id='notification'></div>

<div id="emptycontent" class="hidden">
	<div class="icon-starred"></div>
	<h2><?php p($l->t('No favorites yet')); ?></h2>
	<p><?php p($l->t('Files and folders you mark as favorite will show up here')); ?></p>
</div>

<input type="hidden" name="dir" value="" id="dir">

<div class="nofilterresults emptycontent hidden">
	<div class="icon-search"></div>
	<h2><?php p($l->t('No entries found in this folder')); ?></h2>
	<p></p>
</div>
<table id="filestable" class="list-container <?php p($_['showgridview'] ? 'view-grid' : '') ?>">
	<thead>
		<tr>
			<th id='headerName' class="hidden column-name">
				<div id="headerName-container">
					<a class="name sort columntitle" data-sort="name"><span><?php p($l->t('Name')); ?></span><span class="sort-indicator"></span></a>
				</div>
			</th>
			<th id="headerSize" class="hidden column-size">
				<a class="size sort columntitle" data-sort="size"><span><?php p($l->t('Size')); ?></span><span class="sort-indicator"></span></a>
			</th>
			<th id="headerDate" class="hidden column-mtime">
				<a id="modified" class="columntitle" data-sort="mtime"><span><?php p($l->t('Modified')); ?></span><span class="sort-indicator"></span></a>
				<span class="selectedActions">
				    <a href="" class="delete-selected">
					<img class="svg" alt=""
					     src="<?php print_unescaped(OCP\Template::image_path("core", "actions/delete.svg")); ?>" />
					<?php p($l->t('Delete'))?>
				    </a>
				</span>
			</th>
		</tr>
	</thead>
	<tbody id="fileList">
	</tbody>
	<tfoot>
	</tfoot>
</table>
