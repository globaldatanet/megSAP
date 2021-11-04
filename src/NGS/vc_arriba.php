<?php
require_once(dirname($_SERVER['SCRIPT_FILENAME']) . "/../Common/all.php");

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

$parser = new ToolBase("vc_arriba", "Run fusion detection with Arriba.");

$parser->addInfile("bam", "Input BAM file.", false);

$parser->addOutfile("out_fusions", "Fusion report in TSV format.", false);
$parser->addOutfile("out_discarded", "Discarded fusions in TSV format.", true);
$parser->addOutfile("out_pdf", "Fusion report in PDF format.", true);
$parser->addOutfile("out_bam", "Output BAM file with fusion-supporting reads.", true);

$parser->addInfile("sv", "Optional structural variants from DNA sequencing, in VCF format.", true);

$parser->addString("build", "The genome build to use.", true, "GRCh37");

extract($parser->parse($argv));

if (!in_array($build, ["GRCh37", "GRCh38"]))
{
    trigger_error("Annotation only available for GRCh37/GRCh38!", E_USER_ERROR);
}


//resolve build string used by Arriba
$arriba_str = [
    "GRCh37" => "hg19_hs37d5_GRCh37",
    "GRCh38" => "hg38_GRCh38"
];
$arriba_build = $arriba_str[$build];


//reference files
$genome = genome_fasta($build);
$gtf = get_path("data_folder") . "/dbs/gene_annotations/GRCh37.gtf";
$arriba_ref = get_path("arriba") . "/database";


//run Arriba
$args = [
    "-x", $bam,
    "-o", $out_fusions,
    "-a", $genome,
    "-g", $gtf,
    "-b", "{$arriba_ref}/blacklist_{$arriba_build}_v2.1.0.tsv.gz",
    "-k", "{$arriba_ref}/known_fusions_{$arriba_build}_v2.1.0.tsv.gz",
    "-t", "{$arriba_ref}/known_fusions_{$arriba_build}_v2.1.0.tsv.gz",
    "-p", "{$arriba_ref}/protein_domains_{$arriba_build}_v2.1.0.gff3",
    "-X",
    "-f", "no_genomic_support,read_through,same_gene,intragenic_exonic"
];

if (isset($sv)) $args[] = "-d {$sv}";
if (isset($out_discarded)) $args[] = "-O {$out_discarded}";

$parser->exec(get_path("arriba") . "/arriba", implode(" ", $args));


//generate plot in PDF format
if (isset($out_pdf)) {
    $plot_args = [
        "--annotation={$gtf}",
        "--fusions={$out_fusions}",
        "--output={$out_pdf}",
        "--alignments={$bam}",
        "--cytobands={$arriba_ref}/cytobands_{$arriba_build}_v2.1.0.tsv",
        "--proteinDomains={$arriba_ref}/protein_domains_{$arriba_build}_v2.1.0.gff3"
    ];
    $parser->exec(get_path("arriba") . "/conda_env/bin/Rscript " . get_path("arriba") . "/draw_fusions.R", implode(" ", $plot_args));
}


//extract fusion-supporting reads in extra BAM file
if (isset($out_bam)) {
    $fusions = Matrix::fromTSV($out_fusions);
    $reads_rows = $fusions->getCol($fusions->getColumnIndex("read_identifiers"));
    $reads = explode(",", implode(",", $reads_rows), -1);
    if (count($reads) !== 0)
    {
        $read_ids = $parser->tempFile("_readids.txt");
        file_put_contents($read_ids, implode("\n", $reads));
        $bam_header = $parser->tempFile("_header.txt");
        $bam_records = $parser->tempFile("_records.sam");

        $parser->exec(get_path("samtools"), "view -H {$bam} > {$bam_header}");
        $parser->execPipeline([
            [get_path("samtools"), "view -O SAM {$bam}"],
            ["grep", "-F -f {$read_ids} > {$bam_records}"]
        ], "filter BAM");
        $parser->execPipeline([
            ["cat", "{$bam_header} ${bam_records}"],
            [get_path("samtools"), "view -o {$out_bam}"]
        ], "write BAM");
        $parser->indexBam($out_bam, 1);
    }
}