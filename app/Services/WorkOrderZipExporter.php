<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

class WorkOrderZipExporter
{
    /**
     * Adds one work order's full PDF plus every attachment it owns
     * (images, documents, videos, checklist proofs, ledger bills, approval
     * attachments) into the given zip. Pass $folder to nest everything
     * under a per-WO folder (for a multi-WO zip); leave it null to write
     * at the zip root (for a single-WO zip already named after the WO).
     */
    public function addWorkOrder(ZipArchive $zip, WorkOrder $workOrder, array $sections, ?string $folder = null): void
    {
        $prefix = $folder !== null && $folder !== '' ? $folder.'/' : '';

        $pdf = Pdf::loadView('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => $sections])->output();
        $zip->addFromString("{$prefix}WO Details.pdf", $pdf);

        $this->addCollection($zip, $workOrder->getMedia('images'), "{$prefix}Images");
        $this->addCollection($zip, $workOrder->getMedia('documents'), "{$prefix}Documents");
        $this->addCollection($zip, $workOrder->getMedia('other'), "{$prefix}Documents");
        $this->addCollection($zip, $workOrder->getMedia('videos'), "{$prefix}Videos");

        foreach ($workOrder->dailyChecklists as $checklist) {
            foreach ($checklist->checklistItems as $item) {
                $this->addCollection($zip, $item->getMedia('proof'), "{$prefix}Checklist Proofs");
            }
        }

        foreach ($workOrder->dailyProgressReports as $report) {
            $this->addCollection($zip, $report->getMedia('attachments'), "{$prefix}Progress Report Attachments");
        }

        foreach ($workOrder->ledgers as $ledger) {
            $this->addCollection($zip, $ledger->getMedia('bill'), "{$prefix}Ledger Bills");
        }

        foreach ($workOrder->companyLedgers as $companyLedger) {
            $this->addCollection($zip, $companyLedger->getMedia('bill'), "{$prefix}Company Ledger Bills");
        }

        foreach ($workOrder->approvalRequests as $approval) {
            $this->addCollection($zip, $approval->getMedia('attachment'), "{$prefix}Approval Attachments");
        }
    }

    public function safeName(string $name): string
    {
        return Str::of($name)->replaceMatches('/[\/\\\\:*?"<>|]/', '-')->toString();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Media>  $mediaItems
     */
    private function addCollection(ZipArchive $zip, $mediaItems, string $folder): void
    {
        $usedNames = [];

        foreach ($mediaItems as $media) {
            $path = $media->getPath();

            if (! is_file($path)) {
                continue;
            }

            $name = $this->uniqueName($media->file_name, $usedNames);
            $usedNames[] = $name;
            $zip->addFile($path, "{$folder}/{$name}");
        }
    }

    private function uniqueName(string $fileName, array $used): string
    {
        if (! in_array($fileName, $used, true)) {
            return $fileName;
        }

        $base = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $i = 1;

        do {
            $candidate = $extension !== '' ? "{$base} ({$i}).{$extension}" : "{$base} ({$i})";
            $i++;
        } while (in_array($candidate, $used, true));

        return $candidate;
    }
}
