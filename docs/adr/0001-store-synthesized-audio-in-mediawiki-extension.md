# 0001. Store synthesized audio in MediaWiki extension {#adr_0001}

Date: 2020-03-09

## Status

accepted

## Context

The original implementation of Wikispeech stored the synthesized audio
as files in a folder within the Speechoid service (in the
wikispeech-server sub-service). The paths to these files, together
with the related metadata were then passed on as a response to the
MediaWiki extension.

This implementation had a few identified drawbacks: Wikimedia
infrastructure expects files to be stored in [Swift] rather than as
files on disk, supporting this would require implementing Swift
storage in the Speechoid service.  There is a desire to keep the
Speechoid service stateless, persistent storage of synthesized files
within the service runs counter to this.  The utterance metadata was
not stored, requiring that each sentence always be re-synthesized
unless cached together with the file path.

While Wikimedia requires Swift many other MediaWiki installations
might not be interested in that. It is therefore important with a
solution where the file storage backend can be changed as desired
through the configs.

Due to [RevisionDelete] none of the content (words) of any segment
anywhere should be stored anywhere, e.g. in a table, since these must
then not be publicly queryable, and to include mechanisms preventing
non-public segments from being synthesized.

We have an interest in storing the utterance audio for a long time to
avoid the expensive operation of synthesizing segments on demand, but
we still want a mechanism that flush stored utterances after a given
period of time. If a user makes a change to a text segment, it is
unlikely that the previous revision of that segment is used in another
article and could thus be instantly flushed. There is also the case
where we want to flush to trigger re-synthesizing segments when a word
is added to or updated in the phonetic lexicon, as that would improve
the resulting synthesized speech.

Re-use of utterance audio across a site (or many sites) is desirable,
but likely to be rare (largely limited to headings and shorter
phrases). What will likely be more common is re-use of utterance audio
across multiple revisions of the same page. If a single segment is
edited then all other segments, and their corresponding audio, remain
valid. For this reason utterance audio should not be tied to a given
page or revision.

## Decision

Files are only temporarily stored within Speechoid.

When a segment is synthesized, or when the audio is retrieved, a check
must be performed to ensure it corresponds to a page revision which
has not been suppressed through RevisionDelete. A segment is
represented by a hash to satisfy RevisionDelete requirements on public
tables. The segment hash should only be constructed from its
contents. For the sake of RevisionDelete the link to the synthesised
audio should never be exposed to the end user.

The MediaWiki extension parses the response from Speechoid, fetches
the synthesized audio and stores this as a file using the provided
[FileBackend] functionality. The corresponding utterance metadata is
stored as a JSON file. Both files share the same base filename.

An expiry date is attached to each stored Speechoid response to allow
lexicon updates to propagate and for the flushing of outdated
segments.

## Consequences

Garbage collection of the temporary files in Speechoid must be
ensured.

Since segment hashing only relies on content, utterances can be
re-used across revisions and pages.

A database table must be introduced for storing the basic information
needed to identify the unique call to Speechoid. These are identified
to be language, voice, segment hash. The information in each row can
be used to generate the unique filename for the audio and JSON files.

Lexicon updates will no longer have a direct effect across all already
synthesized audio. The expiry date (or storage date) attached to each
stored utterance ensures such updates are included at a later time
point though. Mechanisms for removing stored utterances before its
expiry time may be needed if a lexicon update can be tied to a
specific segment.

Since the database contains nothing site specific it could be shared
across multiple sites, as can the stored files.

[RevisionDelete]: https://www.mediawiki.org/wiki/Manual:RevisionDelete
[Swift]: https://docs.openstack.org/swift/latest/
[FileBackend]: https://www.mediawiki.org/wiki/Manual:FileBackend.php
