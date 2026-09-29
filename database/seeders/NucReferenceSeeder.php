<?php

namespace Database\Seeders;

use App\Models\Curriculum\NucDiscipline;
use App\Models\SourceDocument;
use Illuminate\Database\Seeder;

/**
 * The NUC reference data: the seventeen CCMAS disciplines and the source
 * document behind each one.
 *
 * This data existed only in the live database, having been loaded directly
 * rather than committed as a seeder. A fresh environment therefore had no
 * disciplines and no provenance for them, which is what made the reference
 * data unreproducible and the architecture test that checks it impossible to
 * run honestly. Values are taken from production unchanged.
 *
 * Idempotent: keyed on the natural key (discipline code, document title) so
 * re-running updates rather than duplicating.
 */
class NucReferenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::disciplines() as $row) {
            NucDiscipline::updateOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'description' => $row['description'],
                    'nuc_document' => $row['nuc_document'], 'status' => $row['status']],
            );
        }

        foreach (self::documents() as $row) {
            SourceDocument::updateOrCreate(
                ['title' => $row['title']],
                ['source_url' => $row['source_url'], 'document_version' => $row['document_version'],
                    'retrieval_date' => $row['retrieval_date'], 'file_path' => $row['file_path'],
                    'file_hash' => $row['file_hash'], 'status' => $row['status'],
                    'document_type' => $row['document_type'], 'extraction_notes' => $row['extraction_notes'],
                    'import_batch_id' => $row['import_batch_id']],
            );
        }
    }

    /** @return list<array<string, string|null>> */
    private static function disciplines(): array
    {
        return [
            ['name' => 'Administration and Management', 'code' => 'ADM', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Agriculture', 'code' => 'AGR', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Allied Health Sciences', 'code' => 'AHS', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Architecture', 'code' => 'ARC', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Arts', 'code' => 'ART', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Basic Medical Sciences', 'code' => 'BMS', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Computing', 'code' => 'CMP', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Communication and Media Studies', 'code' => 'CMS', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Education', 'code' => 'EDU', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Engineering and Technology', 'code' => 'ENG', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Environmental Sciences', 'code' => 'ENV', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Law', 'code' => 'LAW', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Medicine and Dentistry', 'code' => 'MED', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Pharmacy and Pharmaceutical Sciences', 'code' => 'PHA', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Sciences', 'code' => 'SCI', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Social Sciences', 'code' => 'SOC', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
            ['name' => 'Veterinary Medicine', 'code' => 'VET', 'description' => null, 'nuc_document' => 'NUC CCMAS 2023', 'status' => 'active'],
        ];
    }

    /** @return list<array<string, string|null>> */
    private static function documents(): array
    {
        return [
            ['title' => 'Administration and Management', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Administration-and-Management.pdf', 'document_version' => '2026-03', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Agriculture', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Agriculture-2023.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Allied Health Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Allied-Health-Sciences-2023.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Architecture', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Architecture-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Arts', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Arts-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Basic Medical Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Basic-Medical-Sciences-CCMAS-FINAL-December-26-2022.pdf', 'document_version' => '2022', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Computing', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Computing-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Communication and Media Studies', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Communication-and-Media-Studies-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Education', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Education-CCMAS-2023-New.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Engineering and Technology', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Engineering-Technology-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Environmental Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Environmental-Sciences-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Law', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Law-ALL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Medicine and Dentistry', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Medicine-and-Dentistry-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Pharmacy and Pharmaceutical Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Pharmacy-and-Pharmaceutical-Sciences-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Sciences-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Social Sciences', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Social-Sciences-CCMAS-2023-FINAL-A.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
            ['title' => 'Veterinary Medicine', 'source_url' => 'https://www.nuc.edu.ng/wp-content/uploads/2026/03/Veterinary-Medicine-CCMAS-2023-FINAL.pdf', 'document_version' => '2023', 'retrieval_date' => '2026-09-17', 'file_path' => null, 'file_hash' => null, 'status' => 'review', 'document_type' => 'ccmas', 'extraction_notes' => null, 'import_batch_id' => 'batch-2026-09-17'],
        ];
    }
}
