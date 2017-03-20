<?php
/* Copyright (C) 2007-2015 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) ---Put here your own copyright and developer email---
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *   	\file       product/stock/productlot_card.php
 *		\ingroup    stock
 *		\brief      This file is an example of a php page
 *					Initialy built by build_class_from_table on 2016-05-17 12:22
 */

// Change this following line to use the correct relative path (../, ../../, etc)
$res=0;
if (! $res && file_exists("../main.inc.php")) $res=@include '../main.inc.php';					// to work if your module directory is into dolibarr root htdocs directory
if (! $res && file_exists("../../main.inc.php")) $res=@include '../../main.inc.php';			// to work if your module directory is into a subdir of root htdocs directory
if (! $res && file_exists("../../../dolibarr/htdocs/main.inc.php")) $res=@include '../../../dolibarr/htdocs/main.inc.php';     // Used on dev env only
if (! $res && file_exists("../../../../dolibarr/htdocs/main.inc.php")) $res=@include '../../../../dolibarr/htdocs/main.inc.php';   // Used on dev env only
if (! $res) die("Include of main fails");
// Change this following line to use the correct relative path from htdocs
include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php');
include_once(DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php');
include_once(DOL_DOCUMENT_ROOT.'/product/class/product.class.php');
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/product/stock/class/productlot.class.php');

// Load traductions files requiredby by page
$langs->load("stock");
$langs->load("other");
$langs->load("productbatch");

// Get parameters
$id			= GETPOST('id','int');
$action		= GETPOST('action','alpha');
$backtopage = GETPOST('backtopage');
$batch  	= GETPOST('batch','alpha');
$productid  = GETPOST('productid','int');
$ref        = GETPOST('ref','alpha');       // ref is productid_batch

$search_entity=GETPOST('search_entity','int');
$search_fk_product=GETPOST('search_fk_product','int');
$search_batch=GETPOST('search_batch','alpha');
$search_fk_user_creat=GETPOST('search_fk_user_creat','int');
$search_fk_user_modif=GETPOST('search_fk_user_modif','int');
$search_import_key=GETPOST('search_import_key','int');

if (empty($action) && empty($id) && empty($ref)) $action='list';


// Protection if external user
if ($user->societe_id > 0)
{
    //accessforbidden();
}
//$result = restrictedArea($user, 'mymodule', $id);


$object = new ProductLot($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label($object->table_element);

// Load object
//include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';  // Must be include, not include_once. Include fetch and fetch_thirdparty but not fetch_optionals
if ($id || $ref)
{
    if ($ref)
    {
        $tmp=explode('_',$ref);
        $productid=$tmp[0];
        $batch=$tmp[1];
    }
    $object->fetch($id, $productid, $batch);
}

// Initialize technical object to manage hooks of modules. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('productlotcard','globalcard'));


$permissionnote = $user->rights->stock->creer; 		// Used by the include of actions_setnotes.inc.php
$permissiondellink = $user->rights->stock->creer; 	// Used by the include of actions_dellink.inc.php
$permissionedit = $user->rights->stock->creer; 		// Used by the include of actions_lineupdown.inc.php


/*
 * Actions
 */

$parameters=array();
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

if (empty($reshook))
{
	if ($action == 'seteatby' && $user->rights->stock->creer)
	{
	    $newvalue = dol_mktime(12, 0, 0, $_POST['eatbymonth'], $_POST['eatbyday'], $_POST['eatbyyear']);
		$result = $object->setValueFrom('eatby', $newvalue, '', null, 'date', '', $user, 'PRODUCTLOT_MODIFY');
		if ($result < 0) dol_print_error($db, $object->error);
	}
    
	if ($action == 'setsellby' && $user->rights->stock->creer)
	{
	    $newvalue=dol_mktime(12, 0, 0, $_POST['sellbymonth'], $_POST['sellbyday'], $_POST['sellbyyear']);
		$result = $object->setValueFrom('sellby', $newvalue, '', null, 'date', '', $user, 'PRODUCTLOT_MODIFY');
		if ($result < 0) dol_print_error($db, $object->error);
	}
	
	if ($action == 'update_extras')
    {
        // Fill array 'array_options' with data from update form
        $extralabels = $extrafields->fetch_name_optionals_label($object->table_element);
        $ret = $extrafields->setOptionalsFromPost($extralabels, $object, GETPOST('attribute'));
        if ($ret < 0) $error++;
    
        if (! $error)
        {
            // Actions on extra fields (by external module or standard code)
            $hookmanager->initHooks(array('productlotdao'));
            $parameters = array('id' => $object->id);
            $reshook = $hookmanager->executeHooks('insertExtraFields', $parameters, $object, $action); // Note that $action and $object may have been modified by
            // some hooks
            if (empty($reshook)) {
                $result = $object->insertExtraFields();
                if ($result < 0) {
                    $error++;
                }
            } else if ($reshook < 0)
                $error++;
        }
    
        if ($error)
            $action = 'edit_extras';
    }
    
	// Action to add record
	if ($action == 'add')
	{
		if (GETPOST('cancel'))
		{
			$urltogo=$backtopage?$backtopage:dol_buildpath('/stock/list.php',1);
			header("Location: ".$urltogo);
			exit;
		}

		$error=0;

		/* object_prop_getpost_prop */
		
    	$object->entity=GETPOST('entity','int');
    	$object->fk_product=GETPOST('fk_product','int');
    	$object->batch=GETPOST('batch','alpha');
    	$object->fk_user_creat=GETPOST('fk_user_creat','int');
    	$object->fk_user_modif=GETPOST('fk_user_modif','int');
    	$object->import_key=GETPOST('import_key','int');

		if (empty($object->ref))
		{
			$error++;
			setEventMessages($langs->trans("ErrorFieldRequired",$langs->transnoentitiesnoconv("Ref")), null, 'errors');
		}

		if (! $error)
		{
			$result=$object->create($user);
			if ($result > 0)
			{
				// Creation OK
				$urltogo=$backtopage?$backtopage:dol_buildpath('/stock/list.php',1);
				header("Location: ".$urltogo);
				exit;
			}
			{
				// Creation KO
				if (! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else  setEventMessages($object->error, null, 'errors');
				$action='create';
			}
		}
		else
		{
			$action='create';
		}
	}

	// Cancel
	if ($action == 'update' && GETPOST('cancel')) $action='view';

	// Action to update record
	if ($action == 'update' && ! GETPOST('cancel'))
	{
		$error=0;
		
    	$object->entity=GETPOST('entity','int');
    	$object->fk_product=GETPOST('fk_product','int');
    	$object->batch=GETPOST('batch','alpha');
    	$object->fk_user_creat=GETPOST('fk_user_creat','int');
    	$object->fk_user_modif=GETPOST('fk_user_modif','int');
    	$object->import_key=GETPOST('import_key','int');

		if (empty($object->ref))
		{
			$error++;
			setEventMessages($langs->transnoentitiesnoconv("ErrorFieldRequired",$langs->transnoentitiesnoconv("Ref")), null, 'errors');
		}

		if (! $error)
		{
			$result=$object->update($user);
			if ($result > 0)
			{
				$action='view';
			}
			else
			{
				// Creation KO
				if (! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
				else setEventMessages($object->error, null, 'errors');
				$action='edit';
			}
		}
		else
		{
			$action='edit';
		}
	}

	// Action to delete
	if ($action == 'confirm_delete')
	{
		$result=$object->delete($user);
		if ($result > 0)
		{
			// Delete OK
			setEventMessages("RecordDeleted", null, 'mesgs');
			header("Location: ".dol_buildpath('/stock/list.php',1));
			exit;
		}
		else
		{
			if (! empty($object->errors)) setEventMessages(null, $object->errors, 'errors');
			else setEventMessages($object->error, null, 'errors');
		}
	}
}




/*
 * View
 */

llxHeader('','ProductLot','');

$form=new Form($db);


// Put here content of your page

// Example : Adding jquery code
print '<script type="text/javascript" language="javascript">
jQuery(document).ready(function() {
	function init_myfunc()
	{
		jQuery("#myid").removeAttr(\'disabled\');
		jQuery("#myid").attr(\'disabled\',\'disabled\');
	}
	init_myfunc();
	jQuery("#mybutton").click(function() {
		init_myfunc();
	});
});
</script>';


// Part to create
if ($action == 'create')
{
	print load_fiche_titre($langs->trans("Batch"));

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="backtopage" value="'.$backtopage.'">';

	dol_fiche_head();

	print '<table class="border centpercent">'."\n";
	// print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td><td><input class="flat" type="text" size="36" name="label" value="'.$label.'"></td></tr>';
	// 
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldentity").'</td><td><input class="flat" type="text" name="entity" value="'.GETPOST('entity').'"></td></tr>';
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldfk_product").'</td><td><input class="flat" type="text" name="fk_product" value="'.GETPOST('fk_product').'"></td></tr>';
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldbatch").'</td><td><input class="flat" type="text" name="batch" value="'.GETPOST('batch').'"></td></tr>';
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldfk_user_creat").'</td><td><input class="flat" type="text" name="fk_user_creat" value="'.GETPOST('fk_user_creat').'"></td></tr>';
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldfk_user_modif").'</td><td><input class="flat" type="text" name="fk_user_modif" value="'.GETPOST('fk_user_modif').'"></td></tr>';
    print '<tr><td class="fieldrequired">'.$langs->trans("Fieldimport_key").'</td><td><input class="flat" type="text" name="import_key" value="'.GETPOST('import_key').'"></td></tr>';

	print '</table>'."\n";

	dol_fiche_end();

	print '<div class="center"><input type="submit" class="button" name="add" value="'.$langs->trans("Create").'"> &nbsp; <input type="submit" class="button" name="cancel" value="'.$langs->trans("Cancel").'"></div>';

	print '</form>';
}


// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create')))
{
	$res = $object->fetch_optionals($object->id, $extralabels);
	
    //print load_fiche_titre($langs->trans("Batch"));
    
    $head = productlot_prepare_head($object);
	dol_fiche_head($head, 'card', $langs->trans("Batch"), 0, 'barcode');
	
	    
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"] . '?id=' . $object->id, $langs->trans('DeleteBatch'), $langs->trans('ConfirmDeleteBatch'), 'confirm_delete', '', 0, 1);
		print $formconfirm;
	}
	
	print '<table class="border centpercent">'."\n";
	
	$linkback = '<a href="' . DOL_URL_ROOT . '/product/stock/productlot_list.php' . '">' . $langs->trans("BackToList") . '</a>';
	
	// Ref
	print '<tr><td class="titlefield">' . $langs->trans('Batch') . '</td>';
	print '<td colspan="3">';
	print $form->showrefnav($object, 'id', $linkback, 1, 'rowid', 'batch');
	print '</td>';
	print '</tr>';
	
	// Product
    print '<tr><td>'.$langs->trans("Product").'</td><td>';
    $producttmp = new Product($db);
    $producttmp->fetch($object->fk_product);
    print $producttmp->getNomUrl(1, 'stock');
    print '</td></tr>';

    // Eat by
    print '<tr><td>';
    print $form->editfieldkey($langs->trans('Eatby'), 'eatby', $object->eatby, $object, $user->rights->stock->creer, 'datepicker');
    print '</td><td colspan="5">';
    print $form->editfieldval($langs->trans('Eatby'), 'eatby', $object->eatby, $object, $user->rights->stock->creer, 'datepicker');
    print '</td>';
    print '</tr>';
    
    // Sell by
    print '<tr><td>';
    print $form->editfieldkey($langs->trans('Sellby'), 'sellby', $object->sellby, $object, $user->rights->stock->creer, 'datepicker');
    print '</td><td colspan="5">';
    print $form->editfieldval($langs->trans('Sellby'), 'sellby', $object->sellby, $object, $user->rights->stock->creer, 'datepicker');
    print '</td>';
    print '</tr>';
    
    // Other attributes
    $cols = 2;
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_view.tpl.php';
    
	print '</table>';
	
	dol_fiche_end();


	// Buttons
	print '<div class="tabsAction">'."\n";
	$parameters=array();
	$reshook=$hookmanager->executeHooks('addMoreActionsButtons',$parameters,$object,$action);    // Note that $action and $object may have been modified by hook
	if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

	if (empty($reshook))
	{
/*TODO 		if ($user->rights->stock->lire)
		{
			print '<div class="inline-block divButAction"><a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=edit">'.$langs->trans("Modify").'</a></div>'."\n";
		}

		if ($user->rights->stock->supprimer)
		{
			print '<div class="inline-block divButAction"><a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete">'.$langs->trans('Delete').'</a></div>'."\n";
		}
*/
	}
	print '</div>'."\n";
	
	
	print '<a href="'.DOL_URL_ROOT.'/product/reassortlot.php?sref='.urlencode($producttmp->ref).'&search_batch='.urlencode($object->batch).'">'.$langs->trans("ShowCurrentStockOfLot").'</a><br>';
	print '<br>';
	print '<a href="'.DOL_URL_ROOT.'/product/stock/mouvement.php?search_product_ref='.urlencode($producttmp->ref).'&search_batch='.urlencode($object->batch).'">'.$langs->trans("ShowLogOfMovementIfLot").'</a><br>';

}


// End of page
llxFooter();
$db->close();
