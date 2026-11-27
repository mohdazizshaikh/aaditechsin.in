-- Native video provenance remains distinct from existing renderers.
ALTER TABLE growth_media_manifests MODIFY COLUMN renderer ENUM('local-diffusion','template','native-video') NOT NULL;
