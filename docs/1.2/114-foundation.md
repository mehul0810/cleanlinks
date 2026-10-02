# Shared command foundation — issue #114

Baseline: candidate/1.1.2-integrated at 4275febd509a3944d86f5bd3fa54678724b3bda7.

This first slice extracts destination/nofollow validation and persistence into LinkMetadataCommand. It accepts an integer CPT ID and an explicit array of unslashed destination and boolean nofollow; WP_Error data identifies rejected fields. The legacy metabox verifies its existing nonce and delegates. No endpoint is exposed. Future session transports must verify their nonce and permission callback before dispatch; the command always verifies edit_post against the object.

Storage stays in cleanlink_redirect_url and cleanlink_redirect_nofollow. Invalid input does not mutate metadata. Raw ampersands, percent encoding, plus signs and repeated query parameters are retained. Identical metadata retries succeed after persistence readback. This is not create-command idempotency or concurrent-edit protection.

Current access contract: CPT capability_type=post; create/edit uses WordPress post capabilities; edit_post enforces ownership and edit-others. Publish uses publish_posts. Group assignment uses the taxonomy assign_terms capability (default edit_posts); group administration uses manage_categories. Existing CSV export requires manage_options. No capabilities are widened in this slice.

Remaining #114 acceptance: create/update DTOs including title/slug/status/groups; batch row errors and partial-failure receipts; durable request idempotency; concurrent-edit and slug-race handling; endpoint nonce/session and permission tests; role matrix including publish/groups/export; safe recovery from metadata write failure. Readback errors report failures but do not provide transactional rollback across metadata fields. Keep draft and do not mark #114 complete.

Rollback: revert the extraction commit. No schema migration, version change or production UI change.
