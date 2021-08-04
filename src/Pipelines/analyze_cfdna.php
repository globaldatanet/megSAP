<?php

/**
	@page analyze_cfdna
*/
require_once(dirname($_SERVER['SCRIPT_FILENAME'])."/../Common/all.php");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

//parse command line arguments
$parser = new ToolBase("analyze_cfdna", "cfDNA analysis pipeline.");
$parser->addString("folder", "Analysis data folder.", false);
$parser->addString("name", "Base file name, typically the processed sample ID (e.g. 'GS120001_01').", false);
//optional
$parser->addString("tumor_id", "Related tumor processed sample.", true, "");
$parser->addString("tumor_bam", "BAM file of related tumor processed sample.", true, "");
$parser->addString("target", "Custom target region as BED file.", true, "");
$parser->addInfile("monitoring_vcf", "VCF containing patient-specific variants (and IDs).", true);
$parser->addFlag("skip_tumor", "Skip comparison with related tumor sample");
$parser->addInt("base_extend", "Number of bases the target region is extended (default: 60)", true, 60);
$parser->addInfile("system",  "Processing system INI file (automatically determined from NGSD if 'name' is a valid processed sample name).", true);
$steps_all = array("ma", "vc", "db");
$parser->addString("steps", "Comma-separated list of steps to perform:\nma=mapping, vc=variant calling, db=import into NGSD.", true, "ma,vc,db");
$parser->addFlag("annotation_only", "Performs only a reannotation of the already created variant calls.");
$parser->addInt("threads", "The maximum number of threads used.", true, 2);
extract($parser->parse($argv));

//create logfile in output folder if no filepath is provided
if ($parser->getLogFile() == "") $parser->setLogFile($folder."/analyze_cfdna_".date("YmdHis").".log");

//TODO determine values
//low coverage cutoff
$lowcov_cutoff = 100;
//minimum sample genotype correlation
$min_corr = 0.90;

//check steps
$steps = explode(",", $steps);
foreach($steps as $step)
{
	if (!in_array($step, $steps_all)) trigger_error("Unknown processing step '$step'!", E_USER_ERROR);
}

//log server name
list($server) = exec2("hostname -f");
$user = exec('whoami');
$parser->log("Executed on server: ".implode(" ", $server)." as {$user}");

//determine processing system
$sys = load_system($system, $name);

//set up local NGS data copy (to reduce network traffic and speed up analysis)
$parser->execTool("Tools/data_setup.php", "-build {$sys['build']}");


// determine analysis type
if ($sys['type']=="cfDNA (patient-specific)" || $sys['type']=="cfDNA")
{
	$is_patient_specific = $sys['type']=="cfDNA (patient-specific)";
}
else
{
	trigger_error("Unsupported system type '".$sys['type']."'! Pipeline only supports cfDNA samples", E_USER_ERROR);	
}

// for std cfDNA -> use processing system bed as default target
if (!$is_patient_specific && $target == "")
{
	// get target region from processing system
	$target = $sys['target_file'];
}

//database
if (db_is_enabled("NGSD"))
{
	$db = DB::getInstance("NGSD", false);
}

// overwrite tumor_bam and tumor_id if run with -skip_tumor
if ($skip_tumor)
{
	$tumor_bam = "";
	$tumor_id = "";
}

if (isset($db))
{
	// get tumor id from NGSD
	if (!$skip_tumor && ($tumor_id == ""))
	{
		//related samples
		list($sample_name, $ps_num) = explode("_", $name);
		$res_samples = $db->executeQuery("SELECT id, name FROM sample WHERE name=:name", ["name" => $sample_name]);
		if (count($res_samples) !== 1)
		{
			trigger_error("Could not find sample for processed sample {$name}!", E_USER_WARNING);
		}
		$sample_id = $res_samples[0]['id'];
		$res = $db->executeQuery("SELECT * FROM sample_relations WHERE relation='tumor-cfDNA' AND (sample1_id=:sid OR sample2_id=:sid)", array("sid" => $sample_id));

		$psamples = [];
		$ps_ids = [];
		foreach ($res as $row)
		{
			$sample_id_annotation = $row['sample1_id'] != $sample_id ? $row['sample1_id'] : $row['sample2_id'];
			$res = $db->executeQuery("SELECT ps.sample_id, ps.process_id, ps.processing_system_id, ps.quality, sys.id, sys.type, CONCAT(s.name, '_', LPAD(ps.process_id, 2, '0')) as psample, ps.id FROM processed_sample as ps, processing_system as sys, sample as s WHERE ps.sample_id=:sid AND ps.processing_system_id=sys.id AND ps.sample_id=s.id AND sys.type!='cfDNA (patient-specific)' AND (NOT ps.quality='bad') ORDER BY ps.process_id ASC", array("sid" => $sample_id_annotation));
			$psamples = array_merge($psamples, array_column($res, 'psample'));
		}
		if (count($psamples) > 1)
		{
			//TODO: try to identify by available cfdna panel
			trigger_error("Found more than one referenced tumor, using first one: " . implode(" ", $psamples), E_USER_WARNING);
		}
		if (count($psamples) === 0)
		{
			trigger_error("Could not find any related tumor processed sample!", E_USER_WARNING);
		}
		else
		{
			$tumor_id = $psamples[0];
			trigger_error("Tumor id extracted from the NGSD: ${tumor_id}", E_USER_NOTICE);
		}
	}

	// get tumor bam file from NGSD
	if (!$annotation_only && !$skip_tumor && ($tumor_id != "") && ($tumor_bam == ""))
	{
		$psinfo_tumor = get_processed_sample_info($db, $tumor_id);
		$tumor_bam = $psinfo_tumor['ps_bam'];
	}

	// generate bed files for calling (only required for mapping and VC)
	if (!$annotation_only && (in_array("ma", $steps) || in_array("vc", $steps)))
	{
		if ($is_patient_specific)
		{
			if (($target == "") || ($monitoring_vcf == ""))
			{
				// get patient-specific target region from NGSD
				if ($tumor_id == "") trigger_error("Valid tumor id is required to get patient specific target region!", E_USER_ERROR);

				$tumor_ps_id = get_processed_sample_id($db, $tumor_id);
				$sys_id = $db->getValue("SELECT id FROM processing_system WHERE name_short='".$sys["name_short"]."'");

				if ($target == "")
				{
					//TODO: move to temp?
					$target = "{$folder}/{$name}_target_region.bed";
					$bed_content = $db->getValue("SELECT bed FROM cfdna_panels WHERE tumor_id=${tumor_ps_id} AND processing_system_id=${sys_id}");
					file_put_contents($target, $bed_content);
				}
				
				if ($monitoring_vcf == "")
				{
					$monitoring_vcf = "{$folder}/{$name}_cfdna_panel.vcf";
					$vcf_content = $db->getValue("SELECT vcf FROM cfdna_panels WHERE tumor_id=${tumor_ps_id} AND processing_system_id=${sys_id}");
					file_put_contents($monitoring_vcf, $vcf_content);
				}
			}
		}
	}
}
else
{
	trigger_error("WARNING: No connection to the NGSD! All additional information that is stored in the database will be missing in the output!", E_USER_WARNING);
}

//output file names:
//mapping
$bamfile = "{$folder}/{$name}.bam";
$bamfile_raw = "{$folder}/{$name}_before_dedup.bam";
$lowcov_file = "{$folder}/{$name}_{$sys['name_short']}-{$tumor_id}_lowcov.bed";
//variant calling
$vcffile = "{$folder}/{$name}_var.vcf";
//db import
$qc_fastq = "{$folder}/{$name}_stats_fastq.qcML";
$qc_map  = "{$folder}/{$name}_stats_map.qcML";

//check if all required info are available
if (!$annotation_only && (in_array("ma", $steps) || in_array("vc", $steps)))
{
	if ($is_patient_specific && !$skip_tumor)
	{
		if ($tumor_id == "") trigger_error("Required parameter 'tumor_id' missing! Can not perform analysis.", E_USER_ERROR);
		if ($tumor_bam == "") trigger_error("Required parameter 'tumor_bam' missing! Can not perform analysis.", E_USER_ERROR);
	}

	if ($target == "") trigger_error("No target region defined! Can not perform analysis.", E_USER_ERROR);
	if ($is_patient_specific && ($monitoring_vcf == "")) trigger_error("No monitoring VCF defined! Can not perform analysis.", E_USER_ERROR);
}

// for annotation_only: check if all files are available
if ($annotation_only)
{
	if(in_array("vc", $steps) && !file_exists($vcffile))
	{
		trigger_error("VCF for reannotation is missing. Skipping 'vc' step!", E_USER_WARNING);
		if (($key = array_search("vc", $steps)) !== false) unset($steps[$key]);
	}  
}


//extend target region for calling
if (!$annotation_only && (in_array("ma", $steps) || in_array("vc", $steps)))
{
	$target_extended = $parser->tempFile("_extended.bed");
	$pipeline = [];
	$pipeline[] = [ get_path("ngs-bits")."BedExtend", "-in {$target} -n {$base_extend}"];
	$pipeline[] = [ get_path("ngs-bits")."BedSort", ""];
	$pipeline[] = [ get_path("ngs-bits")."BedMerge", "-out {$target_extended}"];
	$parser->execPipeline($pipeline, "extend target region");
}

// for annotation_only: check if all files are available
if ($annotation_only)
{
	if(in_array("vc", $steps) && !file_exists($vcffile))
	{
		trigger_error("VCF for reannotation is missing. Skipping 'vc' step!", E_USER_WARNING);
		if (($key = array_search("vc", $steps)) !== false) unset($steps[$key]);
	} 
}

//mapping
if (in_array("ma", $steps))
{
	//determine input FASTQ files
	$in_for = "{$folder}/*_R1_00?.fastq.gz";
	$in_rev = "{$folder}/*_R2_00?.fastq.gz";
	$in_index = "{$folder}/*_index_*.fastq.gz";
	
	$files1 = glob($in_for);
	$files2 = glob($in_rev);
	$files_index = glob($in_index);
	if (count($files1) != count($files2))
	{
		trigger_error("Found mismatching forward and reverse read file count!\n Forward: $in_for\n Reverse: $in_rev.", E_USER_ERROR);
	}
	if (!empty($files_index) && count($files_index) != count($files1))
	{
		trigger_error("Found mismatching index read file count!", E_USER_ERROR);
	}
	
	$args = [
		"-in_for", implode(" ", $files1),
		"-in_rev", implode(" ", $files2),
		"-clip_overlap",
		"-system", $system,
		"-out_folder", $folder,
		"-out_name", $name,
		"-threads", $threads,
		"--log", $parser->getLogFile()
	];
	if (!empty($files_index)) $args[] = "-in_index " . implode(" ", $files_index);
	$parser->execTool("Pipelines/mapping.php", implode(" ", $args));

	//low-coverage report, based on patient specific positions
	$parser->exec(get_path("ngs-bits")."BedLowCoverage", "-in ${target} -bam ${bamfile} -out ${lowcov_file} -cutoff ${lowcov_cutoff}", true);
	if (db_is_enabled("NGSD"))
	{
		$parser->exec(get_path("ngs-bits")."BedAnnotateGenes", "-in ${lowcov_file} -clear -extend 25 -out ${lowcov_file}", true);
	}

	$parser->exec(get_path("ngs-bits")."MappingQC", "-roi ${target_extended} -in $bamfile -out ${qc_map} -ref ".genome_fasta($sys['build']));
}

//check sample similarity with referenced tumor
if (!($annotation_only || $skip_tumor))
{
	if (($tumor_bam != "") && (in_array("ma", $steps) || in_array("vc", $steps)))
	{
		$output = $parser->exec(get_path("ngs-bits")."SampleSimilarity", "-in {$bamfile} {$tumor_bam} -mode bam -roi {$target_extended}", true);
		$correlation = explode("\t", $output[0][1])[3];
		if ($correlation < $min_corr)
		{
			trigger_error("The genotype correlation of cfDNA and tumor is {$correlation}; it should be above {$min_corr}!", E_USER_ERROR);
		}
	}
	else
	{
		trigger_error("Skipping similarity check!", E_USER_WARNING);
	}
}


//variant calling
if (in_array("vc", $steps))
{
	// skip VC if only annotation should be done
	if (!$annotation_only)
	{
		$args = [
			"-bam", $bamfile,
			"-target", $target_extended,
			"-build", $sys['build'],
			"-folder", $folder."/umiVar",
			"--log", $parser->getLogFile()
		];

		//TODO: model still needed?
		$model = get_path("data_folder")."/dbs/cfdna_caller/{$sys['name_short']}.txt";
		if (file_exists($model))
		{
			$args[] = "-model {$model}";
		}

		if ($is_patient_specific)
		{
			$args[] = "-monitoring_vcf ${monitoring_vcf}";
		}
		$parser->execTool("NGS/vc_cfdna.php", implode(" ", $args));

		//copy vcf (monitoring variants for monitoring / filtered for normal cfDNA)
		if ($is_patient_specific)
		{
			$parser->copyFile("{$folder}/umiVar/{$name}_monitoring.vcf", $vcffile);
		}
		else
		{
			$parser->copyFile("{$folder}/umiVar/{$name}_hq.vcf", $vcffile);
		}

		// sort VCF
		$parser->exec(get_path("ngs-bits")."VcfSort", "-in $vcffile -out $vcffile");
		
		// mark off-target reads
		$parser->exec(get_path("ngs-bits")."VariantFilterRegions", "-in $vcffile -mark off-target -reg ${target} -out $vcffile", true);

		// validate VCF
		$parser->exec(get_path("ngs-bits")."VcfCheck", "-in $vcffile -lines 0 -ref ".genome_fasta($sys['build']), true);
	}

	// annotate VCF 
	$vcffile_annotated = "$folder/${name}_var_annotated.vcf.gz";
	$parser->execTool("Pipelines/annotate.php", "-out_name $name -out_folder $folder -vcf $vcffile -somatic -updown -threads $threads -system $system");

	//add sample info to VCF header
	$tmp_vcf = $parser->tempFile(".vcf");
	$s = Matrix::fromTSV($vcffile_annotated);
	$comments = $s->getComments();
	$comments[] = gsvar_sample_header($name, array("IsTumor" => "yes"), "#", "");
	$s->setComments(sort_vcf_comments($comments));
	$s->toTSV($tmp_vcf);

	// zip and index vcf file
	$parser->exec("bgzip", "-c $tmp_vcf > $vcffile_annotated", true);
	$parser->exec("tabix", "-f -p vcf $vcffile_annotated", true);

	// convert vcf to GSvar
	$gsvar_file = "$folder/${name}.GSvar";
	$args = array("-in $tmp_vcf", "-out $gsvar_file", "-t_col $name", "-cfdna");
	$parser->execTool("NGS/vcf2gsvar_somatic.php", implode(" ", $args));

	if (!$skip_tumor && ($tumor_id != ""))
	{
		//add normal AF & counts
		if (isset($db))
		{
			// get normal sample
			$psinfo_tumor = get_processed_sample_info($db, $tumor_id);
			if ($psinfo_tumor['normal_name'] == "") trigger_error("Normal id not found!", E_USER_ERROR);
			$psinfo_normal = get_processed_sample_info($db, $psinfo_tumor['normal_name']);

			// annotate AF and depth
			$parser->exec(get_path("ngs-bits")."VariantAnnotateFrequency", "-in $gsvar_file -out $gsvar_file -depth -name normal -bam ".$psinfo_normal['ps_bam'], true);
		}
		else
		{
			trigger_error("No NGSD connection! Can not annotate normal AF!", E_USER_WARNING);
		}
	}
	
}



//import to database
if (in_array("db", $steps))
{
	//import QC
	$parser->execTool("NGS/db_import_qc.php", "-id {$name} -files {$qc_fastq} {$qc_map} -force --log ".$parser->getLogFile());

	//check gender
	$parser->execTool("NGS/db_check_gender.php", "-in {$bamfile} -pid {$name} --log ".$parser->getLogFile());	
}

?>
