
# Apex Signer

Allows authors the ability to easily add digital signatures to releases of their Composer packages, and allows users to easily verify the packages and updates downloaded by Composer to help ensure no unauthorized code has made it into the repositories.


## Installation

Install via Composer with:

> `composer require --dev apex/signer`


## Verify Downloads

Verify all available packages within the /vendor/ directory by running the command:

> `./vendor/bin/signer verify`

This will go through all installed Composer packages, and look for a signatures.json file in each.  When found, the merkle tree of the package will be built from the package contents, and compared to the digital signature found within the signatures.json file for that release.  If the author has opted to utilize online signing via the public ledger at https://ledger.apexpl.io/, this will also verify the signing certificate against the ledger.


## Generate Signatures

Within the package root directory, initialize the signer with the command:

> `./vendor/bin/signer init`

Once initialized, the last step just before you run `git commit`, sign the package with the command:

> `./vendor/bin/signer sign [VERSION] [PASSWORD]`

If the version is unspecified, you will be prompted for one, and the version MUST be the same as what the git repository will be tagged with.  This will generate a merkle root of all files being tracked by git, sign it using your private key, and add the necessary entry into the signatures.json file.

Once signed, follow the on screen instructions -- add signatures.json to git, commit and push the repository, then tag it with the same version as you created the signature with.  Your package will now be included when users verify their packages and yours is within their /vendor/ directory.


## Sign and Release Package

Alternatively, you may complete all steps with the one `release` command:

> `./vendor/bin/signer release [VERSION] -m "Your commit message"`

Or by including a file for the commit message instead:

> `./vendor/bin/signer release [VERSION] --file commit.txt`

This will sign the package same as above, but will also commit and push the repository, then tag and push the tags all in one step.  This will check your git settings, and push to the correct remote and branch name.  Run this command once all files are staged for commit, and you are ready to make the release public.


## Support

If you have any questions, issues or feedback, please feel free to drop a note on the <a href="https://reddit.com/r/apexpl/">ApexPl Reddit sub</a> for a prompt and helpful response.


## Follow Apex

Loads of good things coming in the near future including new quality open source packages, more advanced articles / tutorials that go over down to earth useful topics, et al.  Stay informed by joining the <a href="https://apexpl.io/">mailing list</a> on our web site, or follow along on Twitter at <a href="https://twitter.com/mdizak1">@mdizak1</a>.




