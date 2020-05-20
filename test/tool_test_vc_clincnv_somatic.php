<?php
require_once("framework.php");

$name = "vc_clincnv_somatic";
start_test($name);

//Check whether cohort folder for real samples exists in ClinCNV directory
$cohorts_dir = str_replace("Rscript --vanilla ", "", get_path("clincnv")) . "/cohorts";
check(is_dir($cohorts_dir), true);

//prepare input data_folder for test cases
$cov_folder = output_folder()."/cov_folder/";
$cohort_folder1 = output_folder()."/cohorts1/";
$cohort_folder2 = output_folder()."/cohorts2/";
exec2("mkdir -p $cov_folder");
exec2("rm -rf $cov_folder/*");
exec2("tar xzf ".data_folder().$name."_files.tar.gz -C $cov_folder");
create_directory($cohort_folder1);
create_directory($cohort_folder2);

//output folders
$cov_folder_n = $cov_folder ."cov-normal/";
$cov_folder_t = $cov_folder ."cov-tumor/";

$baf_folder = $cov_folder."bafs/";

//file with tumor-normal pairs
$t_n_pair_file = $cov_folder_t."/list_tid-nid.csv";

//system files
$system_file = $cov_folder . "processing_system.ini";
$bed_file = $cov_folder ."target_region.bed";


$t_cov = "{$cov_folder_t}/DX000015_01.cov";
$n_cov = "{$cov_folder_n}/DX000015_02.cov";

$bed_file_off = $cov_folder . "off_target.bed";
$out_file = output_folder().$name."_out.tsv";
$cov_folder_n_off = $cov_folder ."cov-normal_off_target/";
$cov_folder_t_off = $cov_folder ."cov-tumor_off_target/";
$t_cov_off = $cov_folder_t_off . "DX000015_01.cov";
$n_cov_off = $cov_folder_n_off . "DX000015_02.cov";

check_exec("php ".src_folder()."/NGS/{$name}.php -t_id DX000015_01 -n_id DX000015_02 -cov_folder_n $cov_folder_n -cov_folder_t $cov_folder_t -cohort_folder $cohort_folder2 -cov_pairs $t_n_pair_file -out $out_file -t_cov $t_cov -n_cov $n_cov -bed $bed_file -system $system_file -t_cov_off $t_cov_off -n_cov_off $n_cov_off -cov_folder_n_off $cov_folder_n_off -cov_folder_t_off $cov_folder_t_off -bed_off $bed_file_off -baf_folder $baf_folder");

// results depend on the availability of the NGSD 
if (db_is_enabled("NGSD"))
{
	check_file($out_file, data_folder().$name."_out.tsv");
}
else
{
	check_file($out_file, data_folder().$name."_out_noNGSD.tsv");
}

check_file_exists(output_folder()."/DX000015_01-DX000015_02_clonality.png");
check_file_exists(output_folder()."/DX000015_01-DX000015_02_clonalityBarplot.png");
check_file_exists(output_folder()."/DX000015_01-DX000015_02_CNAs_plot.png");

end_test();

?>
