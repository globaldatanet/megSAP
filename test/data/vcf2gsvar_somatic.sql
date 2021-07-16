INSERT INTO variant (chr, start, end, ref, obs, 1000g, gnomad, coding) VALUES ("chr13", 32911888, 32911888, "A", "G", 0.15, 0.16, "BRCA2:NM_000059.3:synonymous_variant:LOW:exon11/27:c.3396A>G:p.Lys1132=:");
INSERT INTO somatic_variant_classification (variant_id, class, comment) VALUES (1, "activating", "I am an deleterious variant class");

INSERT INTO variant (chr, start, end, ref, obs, 1000g, gnomad, coding) VALUES ("chr17", 41226536, 41226536, "G", "T", 0.04, 0.08, "BRCA1:ENST00000309486:stop_gained&splice_region_variant:HIGH:exon13/22:c.3599C>A:p.Ser1200Ter:,BRCA1:ENST00000346315:intron_variant:MODIFIER:intron12/18:c.4357+7885C>A::,BRCA1:ENST00000351666:stop_gained&splice_region_variant:HIGH:exon10/19:c.938C>A:p.Ser313Ter:,BRCA1:ENST00000352993:stop_gained&splice_region_variant:HIGH:exon13/22:c.1061C>A:p.Ser354Ter:,BRCA1:ENST00000354071:intron_variant:MODIFIER:intron12/17:c.4357+7885C>A::,BRCA1:ENST00000357654:stop_gained&splice_region_variant:HIGH:exon14/23:c.4487C>A:p.Ser1496Ter:,BRCA1:ENST00000468300:stop_gained&splice_region_variant:HIGH:exon14/22:c.1175C>A:p.Ser392Ter:,BRCA1:ENST00000471181:stop_gained&splice_region_variant:HIGH:exon15/24:c.4550C>A:p.Ser1517Ter:,BRCA1:ENST00000491747:stop_gained&splice_region_variant:HIGH:exon14/23:c.1175C>A:p.Ser392Ter:,BRCA1:ENST00000493795:stop_gained&splice_region_variant:HIGH:exon13/22:c.4346C>A:p.Ser1449Ter:,BRCA1:ENST00000586385:intron_variant:MODIFIER:intron1/7:c.5-10568C>A::,BRCA1:ENST00000591534:splice_region_variant&5_prime_UTR_variant:LOW:exon2/11:c.-41C>A::,BRCA1:ENST00000591849:intron_variant:MODIFIER:intron1/4:c.-98-24329C>A::");
INSERT INTO somatic_variant_classification (variant_id, class, comment) VALUES (2, "n/a", "I am an unknown somatic variant classification");

INSERT INTO variant (chr, start, end, ref, obs, 1000g, gnomad, coding) VALUES ("chr21", 31744239, 31744239, "C", "A", 0.75, 0.86, "KRTAP13-2:ENST00000399889:missense_variant:MODERATE:exon1/1:c.293G>T:p.Gly98Val:PF05287 [PMG protein]");
INSERT INTO somatic_variant_classification (variant_id, class, comment) VALUES (3, "inactivating", "I am an inactivating variant class");