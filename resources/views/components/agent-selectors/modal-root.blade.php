<div
    class="modal fade ggwp-agents-modal"
    id="ggwpAgentSelectorModal"
    tabindex="-1"
    role="dialog"
    aria-modal="true"
    aria-labelledby="ggwpAgentSelectorModalTitle"
    aria-hidden="true"
    data-agent-selector-modal-root
>
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="small text-uppercase fw-semibold text-secondary" data-agent-selector-modal-eyebrow>Agent Selection</div>
                    <h2 class="modal-title fs-4 mb-0" id="ggwpAgentSelectorModalTitle" data-agent-selector-modal-title>Agent Selection</h2>
                    <p class="text-secondary small mb-0 mt-2" data-agent-selector-modal-description>Select the agents linked to this order.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger small py-2 px-3 d-none" data-agent-selector-modal-error role="alert"></div>
                <div class="text-secondary small mb-3" data-agent-selector-modal-meta aria-live="polite"></div>
                <div class="ggwp-agents-modal__empty d-none" data-agent-selector-modal-empty>No agents are available for this selection.</div>
                <div class="ggwp-agents-modal__grid" data-agent-selector-modal-grid></div>
                <div class="ggwp-agents-modal__grid d-none" data-agent-selector-modal-view-grid></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal" data-agent-selector-modal-close>Close</button>
                <button type="button" class="btn btn-danger" data-agent-selector-modal-save>Save Selection</button>
            </div>
        </div>
    </div>
</div>
