const migrate = (action, offset, postType, redirect_to, clicks ) => {
    const limit = 100; // Number of posts to migrate per AJAX call

    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action,
            offset,
			postType,
			redirect_to,
			clicks,
        }),
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // If success, continue migrating next batch
            migrate(offset + limit); // Recursive call for next batch
        } else {
            // Migration complete
            console.log('Migration completed.');
        }
    })
    .catch(error => {
        console.error('AJAX Error:', error);
    });
};

export { migrate };
