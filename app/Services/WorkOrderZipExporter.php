<?php

namespace App\Services;

use App\Models\ApprovalRequest;
use App\Models\Ticket;
use App\Models\WorkOrder;
use App\Support\Pdf;
use App\Support\WorkOrderPdfSections;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use ZipArchive;

class WorkOrderZipExporter
{
    /**
     * Adds one work order's full PDF plus every attachment belonging to a
     * section the caller is allowed to see (see WorkOrderPdfSections) into
     * the given zip. Pass $folder to nest everything under a per-WO folder
     * (for a multi-WO zip); leave it null to write at the zip root (for a
     * single-WO zip already named after the WO).
     */
    public function addWorkOrder(ZipArchive $zip, WorkOrder $workOrder, array $sections, ?string $folder = null): void
    {
        $prefix = $folder !== null && $folder !== '' ? $folder.'/' : '';

        $pdf = Pdf::loadView('work-orders.pdf.full', ['workOrder' => $workOrder, 'sections' => $sections])->output();
        $zip->addFromString("{$prefix}WO Details.pdf", $pdf);

        foreach (array_keys(WorkOrderPdfSections::SECTIONS) as $section) {
            if (in_array($section, $sections, true)) {
                $this->addSection($zip, $workOrder, $section, $prefix);
            }
        }
    }

    /**
     * Adds only the attachments belonging to one PDF section - the same
     * grouping addWorkOrder() uses, exposed on its own for the per-section
     * "ZIP Download" button on each WO tab. $prefix nests the files under a
     * folder; leave it '' to write at the zip root.
     */
    public function addSection(ZipArchive $zip, WorkOrder $workOrder, string $section, string $prefix = ''): void
    {
        switch ($section) {
            case 'progress':
                $this->addCollection($zip, $workOrder->getMedia('images'), "{$prefix}Images");
                $this->addCollection($zip, $workOrder->getMedia('documents'), "{$prefix}Documents");
                $this->addCollection($zip, $workOrder->getMedia('other'), "{$prefix}Documents");
                $this->addCollection($zip, $workOrder->getMedia('videos'), "{$prefix}Videos");

                foreach ($workOrder->dailyProgressReports as $report) {
                    $this->addCollection($zip, $report->getMedia('attachments'), "{$prefix}Progress Report Attachments");
                }
                break;

            case 'checklist':
                foreach ($workOrder->dailyChecklists as $checklist) {
                    foreach ($checklist->checklistItems as $item) {
                        $this->addCollection($zip, $item->getMedia('proof'), "{$prefix}Checklist Proofs");
                    }
                }
                break;

            case 'ledger':
                foreach ($workOrder->ledgers as $ledger) {
                    $this->addCollection($zip, $ledger->getMedia('bill'), "{$prefix}Ledger Bills");
                }
                break;

            case 'company-ledger':
                foreach ($workOrder->companyLedgers as $companyLedger) {
                    $this->addCollection($zip, $companyLedger->getMedia('bill'), "{$prefix}Company Ledger Bills");
                }
                break;

            case 'approvals':
                foreach ($workOrder->approvalRequests as $approval) {
                    $this->addCollection($zip, $approval->getMedia('attachment'), "{$prefix}Approval Attachments");
                }
                break;

            case 'tickets':
                foreach ($workOrder->tickets as $ticket) {
                    $this->addCollection($zip, $ticket->getMedia('attachments'), "{$prefix}Ticket Attachments");
                }
                break;
        }
    }

    /**
     * Adds just one approval request's own attachments, for the
     * per-request "ZIP Download" button.
     */
    public function addApprovalRequest(ZipArchive $zip, ApprovalRequest $approvalRequest): void
    {
        $this->addCollection($zip, $approvalRequest->getMedia('attachment'), 'Attachments');
    }

    /**
     * Adds just one ticket's own attachments, for the per-ticket
     * "ZIP Download" button.
     */
    public function addTicket(ZipArchive $zip, Ticket $ticket): void
    {
        $this->addCollection($zip, $ticket->getMedia('attachments'), 'Attachments');
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
